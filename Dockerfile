# -----------------------------------------------------------------------------
# This stage builds add required extensions to the base PHP image.
# -----------------------------------------------------------------------------
FROM ghcr.io/burningmantech/php-nginx:8.4.10-alpine3.22 AS php

# Create runtime directories
RUN install -d -o www-data -g www-data -m 775  \
  ./storage/framework/cache                    \
  ./storage/framework/sessions                 \
  ./storage/framework/views                    \
  ./storage/logs                               \
  ;

# -----------------------------------------------------------------------------
# This stage runs composer to build the PHP package dependencies.
# -----------------------------------------------------------------------------
FROM php AS build

# Install Composer
COPY --from=composer:2.8.9 /usr/bin/composer /usr/bin/composer

# Set working directory to application directory
WORKDIR /var/www/application

# Set composer cache directory
ENV COMPOSER_CACHE_DIR=/var/www/composer_cache

COPY ./composer.* ./
# Run composer to get dependencies
# Optimize for production and don't install development dependencies
ARG COMPOSER_AUTH
ENV COMPOSER_AUTH $COMPOSER_AUTH
RUN  /usr/bin/composer install --no-plugins --no-scripts --no-dev --no-autoloader

COPY ./app/           ./app/
COPY ./bootstrap/     ./bootstrap/
COPY ./config/        ./config/
COPY ./database/      ./database/
COPY ./lang/          ./lang/
COPY ./public/        ./public/
COPY ./resources/     ./resources/
COPY ./routes/        ./routes/
COPY ./tests/         ./tests/
COPY ["./artisan", "./phpunit.xml", "./server.php", "./.env.testing", "./"]

RUN /usr/bin/composer dump-autoload --optimize

#
# -----------------------------------------------------------------------------
# This stage builds the application container.
# -----------------------------------------------------------------------------
FROM php AS application

# UTC* causes problems -- use a timezone which does not observe daylight savings and is UTC-7.
ENV TZ=America/Phoenix

# Copy the application with dependencies from the build container
COPY --from=build /var/www/application /var/www/application

# Copy start-nginx script and override supervisor config to use it
COPY ./docker/start-nginx /usr/bin/start-nginx
COPY ./docker/supervisord-nginx.ini /etc/supervisor.d/nginx.ini
COPY ./docker/supervisord-php-octane.ini /etc/supervisor.d/php-octane.ini

# Replace Nginx default site config
COPY ./docker/nginx-default.conf /etc/nginx/http.d/default.conf

# PHP tuning
COPY ./php-inis/production.ini /usr/local/etc/php/conf.d/
#COPY ./php-inis/php-fpm-clubhouse.conf /usr/local/etc/php-fpm.d/zzz-clubhouse.conf

# Laravel task scheduler and queue worker
COPY ./docker/queue-worker.ini /etc/supervisor.d/queue-worker.ini
COPY ["./docker/clubhouse-scheduler", "./docker/clubhouse-worker", "/usr/bin/"]
RUN chmod 555 /usr/bin/clubhouse-scheduler /usr/bin/clubhouse-worker && rm /etc/supervisor.d/php-fpm.ini

# Set working directory to application directory
WORKDIR /var/www/application

# Set ownership of storage directory to www-data user and group
RUN chown -R www-data:www-data storage
# Set file ownership to www-data user and group and change to that user
RUN chown -R www-data:www-data /var/www;
