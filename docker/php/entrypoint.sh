#!/usr/bin/env bash
set -e

cd /var/www

if [ -f composer.json ] && [ ! -d vendor ]; then
    echo "[entrypoint] vendor/ missing — running composer install"
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ -f artisan ] && [ ! -f .env ] && [ -f .env.example ]; then
    echo "[entrypoint] .env missing — copying from .env.example"
    cp .env.example .env
fi

exec "$@"
