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

# SchedulePhoto resolve-discarded: FPM (www-data) дописывает словарь алиасов.
# Bind-mount .cursor/commands часто 644/uid=1000 — без a+w is_writable() = false.
cursor_aliases=/var/www/.cursor/commands/phototext-dish-aliases.md
if [ -f "$cursor_aliases" ]; then
    chmod a+w "$cursor_aliases" 2>/dev/null || true
fi

# Копия в образе: /var/www/html — sticky+world-writable (1777), на ядре
# fs.protected_regular>=1 www-data не может open(O_CREAT) чужой файл,
# даже после chmod 666. file_put_contents при этом даёт Permission denied.
html_aliases=/var/www/html/phototext-dish-aliases.md
if [ -f "$html_aliases" ]; then
    chown www-data:www-data "$html_aliases" 2>/dev/null || true
    chmod ug+rw "$html_aliases" 2>/dev/null || true
fi
