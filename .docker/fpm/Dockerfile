FROM php:8.3-fpm-alpine3.18

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apk add --update --no-cache \
    libgd  \
    libpng \
    libpng-dev  \
    libwebp-dev \
    libjpeg-turbo \
    libjpeg-turbo-dev \
    freetype \
    freetype-dev \
    icu-dev  \
    icu-data-full \
    jpegoptim \
    optipng


RUN docker-php-ext-configure intl
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install opcache mysqli pdo_mysql gd intl

COPY --chown=www-data:www-data . /var/www/musicall
WORKDIR /var/www/musicall
ENV APP_ENV="prod"