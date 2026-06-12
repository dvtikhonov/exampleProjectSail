#!/bin/sh
set -e

mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

for log_file in storage/logs/laravel.log; do
    touch "$log_file"
done

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"
