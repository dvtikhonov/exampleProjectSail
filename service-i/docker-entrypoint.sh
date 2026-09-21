#!/bin/sh
set -e

mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache

touch storage/logs/laravel.log

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Собранные ассеты в образе; hot-файл Vite HMR в runtime не используем.
rm -f public/hot 2>/dev/null || true

if [ ! -f vendor/autoload.php ]; then
    echo "vendor/autoload.php not found — running composer install..."
    composer install --no-interaction --optimize-autoloader --no-progress
fi

# .env смонтирован с хоста (uid 1000, 0644): root в контейнере не может его перезаписать (WSL bind mount).
if [ -f .env ] && ! grep -qE '^APP_KEY=base64:' .env; then
    env_owner="$(stat -c '%u' .env 2>/dev/null || echo '')"
    if [ -n "$env_owner" ] && [ "$(id -u)" != "$env_owner" ]; then
        env_user="$(getent passwd "$env_owner" | cut -d: -f1 || true)"
        if [ -z "$env_user" ]; then
            useradd -u "$env_owner" -M -s /bin/sh envowner 2>/dev/null || true
            env_user="envowner"
        fi
        if [ -n "$env_user" ]; then
            su -s /bin/sh -c 'php artisan key:generate --force --no-interaction' "$env_user" 2>/dev/null || true
        fi
    else
        php artisan key:generate --force --no-interaction 2>/dev/null || true
    fi
fi

# SQLite: файл БД на смонтированном database/ (путь из DB_DATABASE).
mkdir -p database
if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
    chown www-data:www-data database/database.sqlite 2>/dev/null || true
fi

php artisan migrate --force 2>/dev/null || true
php artisan db:seed --force 2>/dev/null || true

exec "$@"
