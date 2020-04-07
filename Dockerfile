# -----------------------------------------------------------------------------
# This stage builds add required extensions to the base PHP image.
# -----------------------------------------------------------------------------
FROM burningman/php-nginx:7.4.4-alpine3.11 as php

# Install the timezone database
RUN apk add --no-cache tzdata;

# Libraries needed to build PHP extensions
RUN apk add --no-cache libxml2-dev libpng-dev libjpeg-turbo-dev libwebp-dev;

# Configure GD
RUN docker-php-ext-configure gd --with-webp --with-jpeg;

# Install extensions
RUN docker-php-ext-install ctype;
RUN docker-php-ext-install exif;
RUN docker-php-ext-install gd;
RUN docker-php-ext-install json;
RUN docker-php-ext-install mbstring;
RUN docker-php-ext-install pdo;
RUN docker-php-ext-install pdo_mysql;
RUN docker-php-ext-install tokenizer;
RUN docker-php-ext-install xml;

# Remove development versions of libraries, and install the runtime versions
RUN apk del libxml2-dev libpng-dev libjpeg-turbo-dev libwebp-dev;
RUN apk add --no-cache libxml2 libpng libjpeg-turbo libwebp;

# Set storage directory permissions
RUN install -d -o www-data -g www-data -m 775  \
    ./storage/framework/cache                  \
    ./storage/framework/sessions               \
    ./storage/framework/views                  \
    ./storage/logs                             \
    ;


# -----------------------------------------------------------------------------
# This stage adds composer to the base PHP image
# -----------------------------------------------------------------------------
FROM php as composer

# Copy the install script, run it, delete it
COPY ./docker/install_composer /docker_install/install
RUN /docker_install/install && rm -rf /docker_install;


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
COPY ./artisan        ./
COPY ./composer.*     ./
COPY ./phpunit.xml    ./
COPY ./server.php     ./
COPY ./.env.testing   ./


# -----------------------------------------------------------------------------
# This stage runs composer to build the PHP package dependencies.
# -----------------------------------------------------------------------------
FROM composer as build

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
RUN php composer.phar install       \
    --no-plugins --no-scripts       \
    --optimize-autoloader --no-dev  \
    ;


# -----------------------------------------------------------------------------
# This stage runs composer to build additional dependencies for development.
# -----------------------------------------------------------------------------
FROM composer as development

# Copy the application source from the source container
COPY --from=source /var/www/application /var/www/application

# Set working directory to application directory
WORKDIR /var/www/application

# Set composer cache directory
ENV COMPOSER_CACHE_DIR=/var/www/composer_cache

# Set file ownership to www-data user and group and change to that user
RUN chown -R www-data:www-data /var/www;
USER www-data

# Copy the composer cache from the build container
COPY --from=build /var/www/composer_cache /var/www/composer_cache

# Run composer to get dependencies
ARG COMPOSER_AUTH
ENV COMPOSER_AUTH $COMPOSER_AUTH
RUN php composer.phar install --no-plugins --no-scripts;


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

# Laravel task scheduler and queue worker
COPY ./docker/queue-worker.ini /etc/supervisor.d/queue-worker.ini
COPY ./docker/clubhouse-scheduler /usr/bin/clubhouse-scheduler
COPY ./docker/clubhouse-worker /usr/bin/clubhouse-worker

RUN chmod 755 /usr/bin/clubhouse-scheduler /usr/bin/clubhouse-worker

# Set working directory to application directory
WORKDIR /var/www/application

# Set ownership of storage directory to www-data user and group
RUN chown -R www-data:www-data storage;
