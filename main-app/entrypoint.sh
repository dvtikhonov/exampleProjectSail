#!/bin/sh
set -e

# Ожидание доступности БД (если используете MySQL)
# /usr/local/bin/wait-for-it.sh host.docker.internal:3306 -t 30

# Генерация ключа приложения, если отсутствует
if ! grep -q "^APP_KEY=" .env || [ -z "$(grep "^APP_KEY=" .env | cut -d= -f2)" ]; then
    php artisan key:generate --force
fi

# Генерация ключей Passport, если отсутствуют
if [ ! -f storage/oauth-private.key ] || [ ! -f storage/oauth-public.key ]; then
    php artisan passport:keys --force
fi

# Создание персонального клиента (требует БД)
if [ -f storage/oauth-private.key ]; then
    php artisan passport:client --personal --no-interaction || true
fi

# Создание необходимых директорий storage
mkdir -p storage/logs
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache

# Установка прав на storage и bootstrap/cache (для смонтированных томов)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Ключи Passport требуют более строгих прав (только владелец может читать/писать)
chmod 600 storage/oauth-private.key
chmod 644 storage/oauth-public.key

# Запуск supervisor
exec /usr/bin/supervisord -c /etc/supervisord.conf
