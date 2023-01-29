FROM php:8.1-fpm-alpine3.16

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apk add --update --no-cache libgd libpng-dev icu-dev icu-data-full

RUN docker-php-ext-configure intl

RUN docker-php-ext-install mysqli pdo_mysql gd intl


COPY --chown=www-data:www-data . /var/www/musicall
WORKDIR /var/www/musicall
ENV APP_ENV="prod"