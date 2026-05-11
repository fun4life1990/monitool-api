FROM php:8.4-fpm-alpine

ARG WWW_UID=1000
ARG WWW_GID=1000

RUN apk add --no-cache \
        bash \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        linux-headers \
        autoconf \
        g++ \
        make \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        bcmath \
        opcache \
        pcntl \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug \
    && apk del autoconf g++ make $PHPIZE_DEPS \
    && rm -rf /tmp/pear /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY php.ini      /usr/local/etc/php/conf.d/zz-app.ini
COPY xdebug.ini   /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN if [ "$(id -u www-data)" != "$WWW_UID" ]; then \
        deluser www-data && \
        addgroup -g "$WWW_GID" www-data && \
        adduser  -u "$WWW_UID" -G www-data -s /bin/sh -D www-data; \
    fi

WORKDIR /var/www

USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]
