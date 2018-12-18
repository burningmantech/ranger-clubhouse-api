#
# This stage runs composer to build the PHP package dependencies
#
FROM composer:1.7.3 as composer

# Run composer in app directory to get dependencies
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
RUN mkdir -p ./storage
RUN chmod -R 777 ./storage
RUN composer install --optimize-autoloader --no-dev


#
# This stage builds the application container
#
FROM burningman/php-nginx:7.2-alpine3.8

# Copy the install script, run it, delete it
COPY ./docker/install /docker_install/install
RUN /docker_install/install && rm -rf /docker_install

# Copy the application with dependencies from the composer container
COPY --from=composer /var/www/application /var/www/application

# Set working directory to application directory
WORKDIR /var/www/application

# Optimize configuration loading
RUN php artisan config:cache;
RUN php artisan route:cache;

# Replace Nginx default site config
COPY ./docker/nginx-default.conf /etc/nginx/conf.d/default.conf
