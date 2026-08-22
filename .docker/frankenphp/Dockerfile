FROM dunglas/frankenphp:1.12-php8.5-alpine

RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    pdo_mysql \
    zip

# Not PHP extensions, so install-php-extensions cannot supply them:
#  - icu-data-full: Alpine ships English-only ICU data, intl would format French dates as English
#  - jpegoptim / optipng: LiipImagine post-processors, see config/packages/liip_imagine.yaml
#  - shadow: usermod and groupmod for the UID remap below
RUN apk add --no-cache \
    shadow \
    icu-data-full \
    jpegoptim \
    optipng

# Files written from inside the container land on the host as this UID, so it has to match
# the host user or everything generated (var/cache, migrations, composer.lock) comes back
# root owned. Hardcoded rather than an ARG: nothing passes build args, so an ARG only
# suggested a flexibility that was never wired up.
RUN usermod -u 1000 www-data \
 && groupmod -g 1000 www-data \
 # Lets a non-root process bind 80 and 443.
 && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
 && chown -R www-data:www-data /data/caddy /config/caddy

USER www-data
WORKDIR /var/www/musicall

# No COPY on purpose: compose bind mounts the working tree over this path, so anything
# copied in would be shadowed at startup. Dev-only image, unusable without that mount.
