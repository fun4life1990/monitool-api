# syntax=docker/dockerfile:1.7

# ─── Stage 1: composer install (no dev) ───────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader \
    && rm -rf /root/.composer/cache

# ─── Stage 2: runtime (php-fpm + nginx + supervisor) ──────────────────────
FROM php:8.4-fpm-alpine AS runtime

ARG WWW_UID=1000
ARG WWW_GID=1000

RUN apk add --no-cache \
        bash \
        nginx \
        supervisor \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        linux-headers \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        bcmath \
        opcache \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear /var/cache/apk/*

COPY docker/prod/php.ini       /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/prod/php-fpm.conf  /usr/local/etc/php-fpm.d/zz-pool.conf
COPY docker/prod/nginx.conf    /etc/nginx/http.d/default.conf
COPY docker/prod/supervisord.conf /etc/supervisord.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN if [ "$(id -u www-data)" != "$WWW_UID" ]; then \
        deluser www-data && \
        addgroup -g "$WWW_GID" www-data && \
        adduser  -u "$WWW_UID" -G www-data -s /bin/sh -D www-data; \
    fi

WORKDIR /var/www

COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data . ./

RUN mkdir -p storage/framework/{cache,sessions,views} \
             storage/logs \
             bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && mkdir -p /run/nginx \
    && chown -R www-data:www-data /run/nginx /var/lib/nginx /var/log/nginx

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
