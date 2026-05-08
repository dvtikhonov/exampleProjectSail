#!/bin/bash
set -e

# Ждём, пока база данных поднимется (опционально)
# Можно раскомментировать, если используете MySQL в docker-compose
# while ! nc -z mysql 3306; do sleep 1; done

# Генерация APP_KEY, если отсутствует
if [ ! -f .env ] || ! grep -q "^APP_KEY=" .env || [ -z "$(grep "^APP_KEY=" .env | cut -d '=' -f2)" ]; then
    php artisan key:generate --force
fi

# Генерация ключей Passport, если отсутствуют
if [ ! -f storage/oauth-private.key ]; then
    php artisan passport:install --force
    # Устанавливаем правильные права (владелец + группа, остальным запрещено)
        chmod 660 storage/oauth-private.key storage/oauth-public.key
fi

# Выполняем миграции (если нужно)
# php artisan migrate --force

# Запускаем переданную команду (CMD)
exec "$@"
