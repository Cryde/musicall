FROM php:8.5-cli-alpine3.24

# The official php images do not ship install-php-extensions, unlike dunglas/frankenphp,
# so it is copied in. It resolves each extension's build dependencies, compiles, then
# removes them again, which is why there is no build-base or autoconf here.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Same list as .docker/frankenphp/Dockerfile, plus pcov: keep the two in sync.
# pcov rather than xdebug, matching what CI uses for coverage.
RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    pdo_mysql \
    zip \
    pcov

# Not PHP extensions, so install-php-extensions cannot supply them:
#  - icu-data-full: Alpine ships English-only ICU data, intl would format French dates as English
#  - jpegoptim / optipng: LiipImagine post-processors, see config/packages/liip_imagine.yaml
#  - git: composer needs it for anything not installed from a dist archive
#  - shadow: usermod and groupmod for the UID remap below
RUN apk add --no-cache \
    git \
    icu-data-full \
    jpegoptim \
    optipng \
    shadow

# Files written from inside the container land on the host as this UID, so it has to match
# the host user or everything generated (var/cache, migrations, composer.lock) comes back
# root owned.
RUN usermod -u 1000 www-data \
 && groupmod -g 1000 www-data

USER www-data
WORKDIR /var/www/musicall

# No COPY on purpose: compose bind mounts the working tree over this path, so anything
# copied in would be shadowed at startup. Dev-only image, unusable without that mount.
