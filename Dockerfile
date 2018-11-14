FROM composer:1.7.3 as composer
RUN docker-php-ext-install pdo pdo_mysql
WORKDIR /var/www/application
COPY . .
RUN composer install --optimize-autoloader --no-dev

FROM burningman/php-nginx:7.2-alpine3.8
RUN docker-php-ext-install pdo pdo_mysql
COPY --from=composer /var/www/application /var/www/application
