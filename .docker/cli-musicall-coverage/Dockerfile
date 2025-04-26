FROM php:8.3-cli-alpine3.21

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# RUN apk add --update --no-cache git bash


RUN apk add --update --no-cache build-base autoconf libgd libzip-dev libpng-dev icu-data-full icu-dev
RUN pecl install pcov  && docker-php-ext-enable pcov

RUN docker-php-ext-configure intl


RUN docker-php-ext-install mysqli pdo_mysql gd intl

WORKDIR /var/www