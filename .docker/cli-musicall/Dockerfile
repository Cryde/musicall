FROM php:8.4-cli-alpine3.22

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apk add --update --no-cache git
RUN apk add --update --no-cache \
    build-base  \
    autoconf \
    shadow \
    libzip-dev \
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
RUN docker-php-ext-install  \
    mysqli  \
    pdo_mysql  \
    gd  \
    intl

RUN pecl install pcov  && docker-php-ext-enable pcov

ARG USER=www-data
ARG UID=1000

RUN usermod -u ${UID} ${USER}
RUN groupmod -g ${UID} ${USER}

USER ${USER}

COPY . /var/www/musicall
WORKDIR /var/www
