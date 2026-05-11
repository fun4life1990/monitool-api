#!/usr/bin/env bash
set -e

cd /var/www

# Drop any stale cached config from a prior build; rebuild against the
# environment variables that App Platform injects at run-time.
php artisan config:clear  >/dev/null 2>&1 || true
php artisan route:clear   >/dev/null 2>&1 || true
php artisan event:clear   >/dev/null 2>&1 || true
php artisan view:clear    >/dev/null 2>&1 || true

php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
