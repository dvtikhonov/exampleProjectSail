FROM laravelsail/php83-composer:latest

# Устанавливаем Node.js 20.x (LTS) и npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Устанавливаем расширение pdo_mysql
RUN docker-php-ext-install pdo_mysql

# Рабочая директория внутри контейнера
WORKDIR /var/www/html

# Копируем исходники
COPY . .

# Устанавливаем зависимости (без dev-пакетов, composer уже есть в образе)
#RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-req=php
RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-req=php \
    && rm -f vendor/composer/platform_check.php

# Права на storage
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Переключаемся на пользователя sail (для безопасности)
#USER sail

EXPOSE 80
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=80"]
