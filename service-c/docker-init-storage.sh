#!/bin/sh
# Общая подготовка storage для service-c (FPM, schedule:work, queue:work).
# php-fpm и artisan должны писать логи/кэш от www-data — иначе Permission denied.
# Если daily-лог создал root (docker exec / artisan от root), FPM получит Permission denied.

mkdir -p \
    storage/logs \
    storage/app/public/dishes \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

for log_file in storage/logs/laravel.log "storage/logs/max_log-$(date +%Y-%m-%d).log"; do
    touch "$log_file"
done

# setgid: новые файлы в logs наследуют группу www-data (смягчает гонки с root).
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
chmod g+s storage/logs 2>/dev/null || true
