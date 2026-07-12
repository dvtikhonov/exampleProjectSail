#!/bin/sh
set -e

mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

touch storage/logs/laravel.log

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ ! -f vendor/autoload.php ]; then
    echo "vendor/autoload.php not found — running composer install..."
    composer install --no-interaction --optimize-autoloader --no-progress
fi

# .env смонтирован с хоста (uid 1000, 0644): root в контейнере не может его перезаписать (WSL bind mount).
if [ -f .env ] && ! grep -qE '^APP_KEY=base64:' .env; then
    env_owner="$(stat -c '%u' .env 2>/dev/null || echo '')"
    if [ -n "$env_owner" ] && [ "$(id -u)" != "$env_owner" ]; then
        su -s /bin/sh -c 'php artisan key:generate --force --no-interaction' "#${env_owner}" 2>/dev/null || true
    else
        php artisan key:generate --force --no-interaction 2>/dev/null || true
    fi
fi

php artisan migrate --force 2>/dev/null || true

exec "$@"
