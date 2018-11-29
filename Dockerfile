#
# This stage runs composer to build the PHP package dependencies
#
FROM composer:1.7.3 as composer
WORKDIR /var/www/application
COPY . .
RUN composer install --optimize-autoloader --no-dev


#
# This stage builds the application container
#
FROM burningman/php-nginx:7.2-alpine3.8

# Copy the install script, run it, delete it
COPY ./bin/_docker_install /docker_install/install
RUN /docker_install/install && rm -rf /docker_install

# Copy the application with dependencies from the composer container
COPY --from=composer /var/www/application /var/www/application

# Set working directory to application directory
WORKDIR /var/www/application

# Optimize configuration loading
RUN php artisan config:cache;
RUN php artisan route:cache;
