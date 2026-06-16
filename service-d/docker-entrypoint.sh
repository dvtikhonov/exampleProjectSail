#!/bin/sh
set -e

mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

touch storage/logs/laravel.log

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

rm -f public/hot 2>/dev/null || true

SPA_MANIFEST="public/spa-build/manifest.json"
SPA_ENTRY="resources/js/spa-app/app.js"

needs_build=0
if [ ! -f "$SPA_MANIFEST" ]; then
    needs_build=1
elif [ -f "$SPA_ENTRY" ] && [ "$SPA_ENTRY" -nt "$SPA_MANIFEST" ]; then
    needs_build=1
fi

if [ "$needs_build" -eq 1 ]; then
    echo "Building Vite assets for spa-app..."
    npm run build
fi

exec "$@"
