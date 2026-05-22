# service-b/Dockerfile
FROM php:8.4-fpm
#FROM laravelsail/php84-composer:latest

# Установка необходимых системных пакетов и PHP-расширений
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Устанавливаем расширение pdo_mysql
# RUN docker-php-ext-install pdo_mysql

# Рабочая директория внутри контейнера
WORKDIR /var/www/html
#WORKDIR /var/www

# Копируем ВСЕ файлы проекта (vendor на хосте будет проигнорирован через .dockerignore)
COPY . .

# Копируем только composer.json и composer.lock для установки зависимостей (оптимизация)
#COPY composer.json composer.lock ./
COPY composer.json ./

# Диагностика: показать содержимое текущей директории и версию Composer
# RUN echo "=== Current directory contents ===" && ls -la

# Копируем исходники
# Копирование composer файлов (для оптимального кэширования слоёв)
# COPY composer.json composer.lock ./

# Установка зависимостей
#RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-req=php \
#    && rm -f vendor/composer/platform_check.php

# Устанавливаем зависимости, НО без выполнения скриптов (--no-scripts)
#RUN composer install --no-interaction --optimize-autoloader --no-dev \
#        --ignore-platform-req=php --no-scripts \
#    && rm -f vendor/composer/platform_check.php

# Установка зависимостей (Composer сам выполнит post-autoload-dump и artisan package:discover)
#RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-req=php  2>&1 || { echo "Composer install failed!"; exit 1; }
# RUN composer install --no-interaction --optimize-autoloader --no-dev 2>&1 || { echo "Composer install failed!"; exit 1; }
RUN composer install --no-interaction --optimize-autoloader

# Проверка создания vendor/autoload.php
# RUN test -f vendor/autoload.php && echo "autoload.php exists" || (echo "autoload.php NOT found!" && exit 1)


# Устанавливаем зависимости (без dev-пакетов, composer уже есть в образе)
#RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-req=php \
#    && rm -f vendor/composer/platform_check.php
#RUN composer install --no-interaction --optimize-autoloader --no-dev

# Запускаем скрипты композера (они выполнят artisan package:discover)
#RUN composer run-script post-autoload-dump

# Права на storage
#RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
#RUN chown -R www-data:www-data storage bootstrap/cache
# СОЗДАЁМ необходимые папки (если их нет) и ставим права
# RUN mkdir -p storage bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache

# Убедимся, что Laravel всегда видит writable cache/session/view paths.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache


# Переключаемся на пользователя sail (для безопасности)
#USER sail

# Стартуем встроенный сервер (только для разработки!)
# Для production лучше использовать php-fpm + nginx внутри pod-а, но т.к. у нас уже gateway – можно оставить artisan serve
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
