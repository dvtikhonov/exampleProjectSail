# main-app/Dockerfile
FROM php:8.3-fpm
#FROM laravelsail/php83-composer:latest

# Установка необходимых системных пакетов и PHP-расширений
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Устанавливаем расширение pdo_mysql
RUN docker-php-ext-install pdo_mysql

# Рабочая директория внутри контейнера
WORKDIR /var/www/html
#WORKDIR /var/www

# Копируем исходники
# Копирование composer файлов (для оптимального кэширования слоёв)
COPY composer.json composer.lock ./

# Установка зависимостей
#RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-req=php \
#    && rm -f vendor/composer/platform_check.php

# Устанавливаем зависимости, НО без выполнения скриптов (--no-scripts)
RUN composer install --no-interaction --optimize-autoloader --no-dev \
        --ignore-platform-req=php --no-scripts \
    && rm -f vendor/composer/platform_check.php

COPY . .

# Устанавливаем зависимости (без dev-пакетов, composer уже есть в образе)
#RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-req=php \
#    && rm -f vendor/composer/platform_check.php
#RUN composer install --no-interaction --optimize-autoloader --no-dev

# Запускаем скрипты композера (они выполнят artisan package:discover)
RUN composer run-script post-autoload-dump

# Права на storage
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Переключаемся на пользователя sail (для безопасности)
#USER sail

# Стартуем встроенный сервер (только для разработки!)
# Для production лучше использовать php-fpm + nginx внутри pod-а, но т.к. у нас уже gateway – можно оставить artisan serve
CMD php artisan serve --host=0.0.0.0 --port=8000
#EXPOSE 80
#CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=80"]
