# -----------------------------------------------------------------------------
# This stage builds add required extensions to the base PHP image.
# -----------------------------------------------------------------------------
FROM burningman/php-nginx:8.0.9-alpine3.14 as php

RUN apk add --no-cache tzdata libxml2-dev libpng-dev libjpeg-turbo-dev libwebp-dev \
    libxml2 libpng libjpeg-turbo libwebp mysql-client icu-dev libzip-dev zip \
    && docker-php-ext-configure gd \
      --with-webp=/usr/include/    \
      --with-jpeg=/usr/include/    \
    && docker-php-ext-configure exif \
    &&  docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install -j$(nproc) exif \
    && docker-php-ext-install -j$(nproc) pdo \
    && docker-php-ext-install -j$(nproc) pdo_mysql \
    && docker-php-ext-install -j$(nproc) zip \
    && docker-php-ext-configure opcache --enable-opcache \
    && docker-php-ext-install -j$(nproc) opcache \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) intl \
    && apk del libxml2-dev libpng-dev libjpeg-turbo-dev libwebp-dev \
    && install -d -o www-data -g www-data -m 775  \
    ./storage/framework/cache                  \
    ./storage/framework/sessions               \
    ./storage/framework/views                  \
    ./storage/logs \
    && (curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer)

# -----------------------------------------------------------------------------
# This stage contains source files.
# We use this so we don't have to enumerate the sources to copy more than once.
# -----------------------------------------------------------------------------
FROM scratch as source

# Copy the application over
WORKDIR /var/www/application
COPY ./tests/         ./tests/
COPY ./routes/        ./routes/
COPY ./resources/     ./resources/
COPY ./public/        ./public/
COPY ./database/      ./database/
COPY ./config/        ./config/
COPY ./bootstrap/     ./bootstrap/
COPY ./app/           ./app/
COPY ["./artisan", "./composer.json", "./composer.lock", "./phpunit.xml", "./server.php", "./.env.testing", "./"]


# -----------------------------------------------------------------------------
# This stage runs composer to build the PHP package dependencies.
# -----------------------------------------------------------------------------
FROM php as build

# Copy the application source from the source container
COPY --from=source /var/www/application /var/www/application

# Set working directory to application directory
WORKDIR /var/www/application

# Set composer cache directory
ENV COMPOSER_CACHE_DIR=/var/www/composer_cache

# Set file ownership to www-data user and group and change to that user
RUN chown -R www-data:www-data /var/www;
USER www-data

# Run composer to get dependencies
# Optimize for production and don't install development dependencies
ARG COMPOSER_AUTH
ENV COMPOSER_AUTH $COMPOSER_AUTH
RUN  /usr/bin/composer install --no-plugins --no-scripts --optimize-autoloader --no-dev;

# -----------------------------------------------------------------------------
# This stage runs composer to build additional dependencies for development.
# -----------------------------------------------------------------------------
#FROM php as development

# Copy the application source from the source container
#COPY --from=source /var/www/application /var/www/application

# Set working directory to application directory
#WORKDIR /var/www/application

# Set composer cache directory
#ENV COMPOSER_CACHE_DIR=/var/www/composer_cache

# Set file ownership to www-data user and group and change to that user
#RUN chown -R www-data:www-data /var/www;
#USER www-data

# Copy the composer cache from the build container
#COPY --from=build /var/www/composer_cache /var/www/composer_cache

# Run composer to get dependencies
#ARG COMPOSER_AUTH
#ENV COMPOSER_AUTH $COMPOSER_AUTH
#RUN /usr/bin/composer install --no-plugins --no-scripts;


# -----------------------------------------------------------------------------
# This stage builds the application container.
# -----------------------------------------------------------------------------
FROM php as application

# UTC* causes problems -- use a timezone which does not observe daylight savings and is UTC-7.
ENV TZ=America/Phoenix

# Copy the application with dependencies from the build container
COPY --from=build /var/www/application /var/www/application

# Copy start-nginx script and override supervisor config to use it
COPY ./docker/start-nginx /usr/bin/start-nginx
COPY ./docker/supervisord-nginx.ini /etc/supervisor.d/nginx.ini

# Replace Nginx default site config
COPY ./docker/nginx-default.conf /etc/nginx/conf.d/default.conf

# PHP tuning
COPY ./php-inis/production.ini /usr/local/etc/php/conf.d/
COPY ./php-inis/php-fpm-clubhouse.conf /usr/local/etc/php-fpm.d/zzz-clubhouse.conf

# Laravel task scheduler and queue worker
COPY ./docker/queue-worker.ini /etc/supervisor.d/queue-worker.ini
COPY ["./docker/clubhouse-scheduler", "./docker/clubhouse-worker", "/usr/bin/"]
RUN chmod 555 /usr/bin/clubhouse-scheduler /usr/bin/clubhouse-worker

# Set working directory to application directory
WORKDIR /var/www/application

# Set ownership of storage directory to www-data user and group
RUN chown -R www-data:www-data storage && rm -rf /var/cache/apk/*;


