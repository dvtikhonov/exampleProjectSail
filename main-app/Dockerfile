FROM php:8.4-fpm-alpine AS fpm

# Установка системных зависимостей, Node.js, Composer и пр.
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    nodejs \
    npm

# Установка PHP-расширений
RUN docker-php-ext-install pdo_mysql mbstring zip xml bcmath

## Установка Xdebug через pecl с очисткой кэша и обновлением каналов
#RUN pecl install xdebug-3.3.2 \
#    && docker-php-ext-enable xdebug

## Установка Xdebug (автоматически подберёт версию, совместимую с PHP)
#RUN pecl channel-update pecl.php.net \
#    && pecl install xdebug \
#    && docker-php-ext-enable xdebug

## Установка Xdebug (опционально)
#RUN apk add --no-cache $PHPIZE_DEPS \
#    && pecl install xdebug \
#    && docker-php-ext-enable xdebug

## Установка Xdebug из пакета Alpine
#RUN apk add --no-cache php84-pecl-xdebug \
#    && docker-php-ext-enable xdebug

# Установка Xdebug из исходников (рабочий вариант на Alpine)
RUN apk add --no-cache $PHPIZE_DEPS git linux-headers \
    && git clone --branch 3.5.0 --depth 1 https://github.com/xdebug/xdebug.git /tmp/xdebug \
    && cd /tmp/xdebug \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && docker-php-ext-enable xdebug \
    && rm -rf /tmp/xdebug

## Очистка кеша, обновление канала и установка Xdebug 3.4.1
#RUN pecl clear-cache \
#    && pecl channel-update pecl.php.net \
#    && pecl install xdebug-3.4.1 \
#    && docker-php-ext-enable xdebug
#
## Копируем конфиг Xdebug (создадим ниже)
#COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Рабочая директория внутри контейнера
WORKDIR /var/www/html

# Копируем исходники (сначала composer файлы для кеширования)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-scripts

# Копируем весь проект (включая routes, database, и т.д.)
COPY . .

#COPY composer.json composer.lock ./

# Настройка прав
RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
## Создаём необходимые папки и права
#RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache
#RUN chown -R www-data:www-data storage bootstrap/cache

## Если нет .env – создаём из .env.example (или пустой, чтобы artisan не ругался на отсутствие)
#RUN if [ ! -f .env ]; then \
#        if [ -f .env.example ]; then cp .env.example .env; \
#        else echo "APP_KEY=" > .env; \
#        fi \
#    fi

## Устанавливаем зависимости БЕЗ выполнения скриптов (чтобы не пытаться запустить artisan раньше времени)
#ENV COMPOSER_ALLOW_SUPERUSER=1
#RUN composer install --no-interaction --optimize-autoloader --no-scripts

## Отключаем Passport
#RUN sed -i '/Laravel\\Passport\\PassportServiceProvider::class,/s/^/\/\//' config/app.php

## Теперь регистрируем все сервис-провайдеры (включая ArtisanServiceProvider) через package:discover
#RUN php artisan package:discover

## Теперь команда key:generate уже доступна – генерируем ключ
#RUN php artisan key:generate --force

## Включаем Passport обратно
#RUN sed -i '/\/\/Laravel\\Passport\\PassportServiceProvider::class,/s/^\/\///' config/app.php

RUN if [ ! -f .env ]; then cp .env.example .env; fi \
    && sed -i '/Laravel\\Passport\\PassportServiceProvider::class,/s/^/\/\//' config/app.php \
    && php artisan package:discover \
    && sed -i '/\/\/Laravel\\Passport\\PassportServiceProvider::class,/s/^\/\///' config/app.php

# Копируем конфиги Nginx и PHP-FPM
COPY nginx.conf /etc/nginx/nginx.conf
#COPY default.conf /etc/nginx/http.d/default.conf
COPY default.conf /etc/nginx/conf.d/default.conf
# или /etc/nginx/conf.d/default.conf
COPY php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY supervisord.conf /etc/supervisord.conf

# Скрипт ожидания готовности MySQL (опционально)
COPY wait-for-it.sh /usr/local/bin/wait-for-it.sh
RUN chmod +x /usr/local/bin/wait-for-it.sh

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]

EXPOSE 8000

# Запускаем supervisor (управляет nginx и php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

## ВСТАВЬТЕ ЭТОТ ENTRYPOINT (исправляет генерацию ключей и Passport)
#COPY entrypoint.sh /usr/local/bin/
#RUN chmod +x /usr/local/bin/entrypoint.sh
#ENTRYPOINT ["entrypoint.sh"]

#CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
