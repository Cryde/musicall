FROM php:8.1-cli-alpine3.17

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
RUN docker-php-ext-install mysqli pdo_mysql gd intl

WORKDIR /var/www