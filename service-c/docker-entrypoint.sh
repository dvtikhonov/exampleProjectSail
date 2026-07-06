#!/bin/sh
set -e

mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

for log_file in storage/logs/laravel.log storage/logs/messMax.log; do
    touch "$log_file"
done

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Mini-app в MAX (web/desktop/mobile) через туннель: только production build.
# Vite dev (public/hot → localhost:5174) снаружи недоступен.
rm -f public/hot 2>/dev/null || true
if [ ! -f public/max-build/manifest.json ]; then
    echo "Building Vite assets for max-app (required for MAX via tunnel)..."
    npm run build
fi

php artisan schedule:work > storage/logs/schedule-work.log 2>&1 &

exec "$@"
