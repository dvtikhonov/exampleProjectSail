# service-a/Dockerfile
FROM php:8.4-fpm
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

# Устанавливаем Xdebug из исходников (pecl install ненадёжен в CI).
RUN apt-get update && apt-get install -y --no-install-recommends $PHPIZE_DEPS git \
    && git clone --branch 3.5.0 --depth 1 https://github.com/xdebug/xdebug.git /tmp/xdebug \
    && cd /tmp/xdebug \
    && phpize \
    && ./configure \
    && make -j"$(nproc)" \
    && make install \
    && docker-php-ext-enable xdebug \
    && rm -rf /tmp/xdebug \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Рабочая директория внутри контейнера
WORKDIR /var/www/html
#WORKDIR /var/www

# Копируем исходники
# Копирование composer файлов (для оптимального кэширования слоёв)
COPY service-a/composer.json service-a/composer.lock ./
COPY shared /var/www/shared

# Установка зависимостей
#RUN composer install --no-interaction --optimize-autoloader --no-dev

# Устанавливаем зависимости, НО без выполнения скриптов (--no-scripts)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress --no-dev --no-scripts

COPY service-a .

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Запускаем скрипты композера (они выполнят artisan package:discover)
RUN composer run-script post-autoload-dump

# Переключаемся на пользователя sail (для безопасности)
#USER sail

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
#EXPOSE 80
#CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=80"]
