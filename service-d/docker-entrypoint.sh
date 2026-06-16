#!/bin/sh
set -e

mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

touch storage/logs/laravel.log

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

rm -f public/hot 2>/dev/null || true
if [ ! -f public/spa-build/manifest.json ]; then
    echo "Building Vite assets for spa-app..."
    npm run build
fi

exec "$@"
