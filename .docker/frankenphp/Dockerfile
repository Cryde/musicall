FROM dunglas/frankenphp:1.9-php8.4-alpine

RUN apk add --update --no-cache \
    libpq-dev \
    libzip-dev \
    shadow

RUN docker-php-ext-install \
    opcache  \
    bcmath  \
    zip  \
    pdo  \
    pdo_mysql

ARG USER=www-data
ARG UID=1000

RUN usermod -u ${UID} ${USER}
RUN groupmod -g ${UID} ${USER}

RUN \
	# Use "adduser -D ${USER}" for alpine based distros
	#adduser -D ${USER}; \
	# Add additional capability to bind to port 80 and 443
	setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp; \
	# Give write access to /data/caddy and /config/caddy
	chown -R ${USER}:${USER} /data/caddy && chown -R ${USER}:${USER} /config/caddy

USER ${USER}

COPY . /var/www/musicall
WORKDIR /var/www/musicall
