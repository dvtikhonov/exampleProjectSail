#!/bin/sh
set -e

mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

touch storage/logs/laravel.log

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

php artisan migrate --force 2>/dev/null || true

php artisan filament:upgrade --no-interaction 2>/dev/null || true

exec "$@"
