#
# This stage runs composer to build the PHP package dependencies
#
FROM composer:1.7.3 as composer

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

# Make storage directory
RUN install -d -o www-data -g www-data -m 775 \
    ./storage/framework/cache    \
    ./storage/framework/sessions \
    ./storage/framework/views    \
    ./storage/logs               \
    ;

# Run composer in app directory to get dependencies
RUN composer install --optimize-autoloader --no-dev;


#
# This stage builds the application container
#
FROM burningman/php-nginx:7.2-alpine3.8

# Copy the install script, run it, delete it
COPY ./docker/install /docker_install/install
RUN /docker_install/install && rm -rf /docker_install;

# Copy start-nginx script and override supervisor config to use it
COPY ./docker/start-nginx /usr/bin/start-nginx
COPY ./docker/supervisord-nginx.ini /etc/supervisor.d/nginx.ini

# Copy the application with dependencies from the composer container
COPY --from=composer /var/www/application /var/www/application

# Replace Nginx default site config
COPY ./docker/nginx-default.conf /etc/nginx/conf.d/default.conf

# Set working directory to application directory
WORKDIR /var/www/application
