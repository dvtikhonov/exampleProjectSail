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

# Установка PHP-расширений (pcntl нужен Laravel Reverb для SIGINT/SIGTERM)
RUN docker-php-ext-install pdo_mysql mbstring zip xml bcmath pcntl


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

## Копируем конфиг Xdebug
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Рабочая директория внутри контейнера
WORKDIR /var/www/html

# Копируем исходники (сначала composer файлы для кеширования)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-scripts

# Копируем npm-манифесты и устанавливаем фронтенд-зависимости
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

# Копируем весь проект (включая routes, database, и т.д.)
COPY . .

# Настройка прав
RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Nginx worker runs as www-data and needs write access to FastCGI temp buffers.
RUN mkdir -p \
        /var/lib/nginx/tmp/client_body \
        /var/lib/nginx/tmp/proxy \
        /var/lib/nginx/tmp/fastcgi \
        /var/lib/nginx/tmp/uwsgi \
        /var/lib/nginx/tmp/scgi \
        /run/nginx \
    && chown -R www-data:www-data /var/lib/nginx /run/nginx

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

