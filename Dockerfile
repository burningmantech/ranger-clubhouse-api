#
# This stage contains source files.
# We use this so we don't have to enumerate the sources to copy more than once.
#
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
COPY ./package.json   ./
COPY ./phpunit.xml    ./
COPY ./server.php     ./
COPY ./webpack.mix.js ./
COPY ./yarn.lock      ./


#
# This stage runs composer to build the PHP package dependencies.
#
FROM composer:1.8.4 as build

# Copy the application source from the source container
COPY --from=source /var/www/application /var/www/application

# Set working directory to application directory
WORKDIR /var/www/application

# Install storage directory
RUN install -d -o www-data -g www-data -m 775  \
    ./storage/framework/cache                  \
    ./storage/framework/sessions               \
    ./storage/framework/views                  \
    ./storage/logs                             \
    ;

# Set composer cache directory
ENV COMPOSER_CACHE_DIR=/var/cache/composer

# Run composer to get dependencies.
# Optimize for production and don't install development dependencies.
RUN composer install --optimize-autoloader --no-dev;


#
# This stage builds the development container.
#
FROM composer:1.8.4 as development

# Copy the application source from the source container
COPY --from=source /var/www/application /var/www/application

# Set working directory to application directory
WORKDIR /var/www/application

# Install storage directory
RUN install -d -o www-data -g www-data -m 775  \
    ./storage/framework/cache                  \
    ./storage/framework/sessions               \
    ./storage/framework/views                  \
    ./storage/logs                             \
    ;

# Copy the install script, run it, delete it
COPY ./docker/install /docker_install/install
RUN /docker_install/install && rm -rf /docker_install;

# Set composer cache directory
ENV COMPOSER_CACHE_DIR=/var/cache/composer

# Copy the composer cache from the build container
COPY --from=build /var/cache/composer /var/cache/composer

# Run composer to get dependencies
RUN composer install;


#
# This stage builds the application container.
#
FROM burningman/php-nginx:7.2-alpine3.8

# Copy the application with dependencies from the build container
COPY --from=build /var/www/application /var/www/application

# Copy the install script, run it, delete it
COPY ./docker/install /docker_install/install
RUN /docker_install/install && rm -rf /docker_install;

# Copy start-nginx script and override supervisor config to use it
COPY ./docker/start-nginx /usr/bin/start-nginx
COPY ./docker/supervisord-nginx.ini /etc/supervisor.d/nginx.ini

# Replace Nginx default site config
COPY ./docker/nginx-default.conf /etc/nginx/conf.d/default.conf

# PHP tuning
COPY ./php-inis/production.ini /usr/local/etc/php/conf.d/

# Set working directory to application directory
WORKDIR /var/www/application
