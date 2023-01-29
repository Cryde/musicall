FROM php:8.1-cli-alpine3.17

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# RUN apk add --update --no-cache git bash

RUN apk add --update --no-cache libgd libpng-dev

RUN docker-php-ext-install mysqli pdo_mysql gd

WORKDIR /var/www