FROM laravelsail/php84-composer:latest

# Устанавливаем Node.js 20.x (LTS) и npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Устанавливаем расширение pdo_mysql
RUN docker-php-ext-install pdo_mysql

## Установка Xdebug через pecl с очисткой кэша и обновлением каналов
#RUN pecl install xdebug-3.3.2 \
#    && docker-php-ext-enable xdebug

## Установка Xdebug (автоматически подберёт версию, совместимую с PHP)
#RUN pecl channel-update pecl.php.net \
#    && pecl install xdebug \
#    && docker-php-ext-enable xdebug

#RUN apt-get update && apt-get install -y git \
RUN apt-get update && apt-get install -y git \
    && git clone --branch 3.4.1 --depth 1 https://github.com/xdebug/xdebug.git /tmp/xdebug \
    && cd /tmp/xdebug \
    && phpize \
    && ./configure --enable-xdebug \
    && make \
    && make install \
    && docker-php-ext-enable xdebug \
    && rm -rf /tmp/xdebug

## Очистка кеша, обновление канала и установка Xdebug 3.4.1
#RUN pecl clear-cache \
#    && pecl channel-update pecl.php.net \
#    && pecl install xdebug-3.4.1 \
#    && docker-php-ext-enable xdebug


# Копируем конфиг Xdebug (создадим ниже)
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini


# Рабочая директория внутри контейнера
WORKDIR /var/www/html

# Копируем весь проект (включая routes, database, и т.д.)
COPY . .


## Копируем минимальный набор для выполнения artisan команд
#COPY bootstrap/ /var/www/html/bootstrap/
#COPY app/ /var/www/html/app/
#COPY artisan /var/www/html/
## часто требуется для провайдеров
#COPY config/ /var/www/html/config/
# Копируем файлы composer и исходники (отдельными слоями для кеширования)
COPY composer.json composer.lock ./

# Создаём необходимые папки и права
RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache
RUN chown -R www-data:www-data storage bootstrap/cache

# Если нет .env – создаём из .env.example (или пустой, чтобы artisan не ругался на отсутствие)
RUN if [ ! -f .env ]; then \
        if [ -f .env.example ]; then cp .env.example .env; \
        else echo "APP_KEY=" > .env; \
        fi \
    fi

# Устанавливаем зависимости БЕЗ выполнения скриптов (чтобы не пытаться запустить artisan раньше времени)
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-interaction --optimize-autoloader --no-scripts

# Отключаем Passport
RUN sed -i '/Laravel\\Passport\\PassportServiceProvider::class,/s/^/\/\//' config/app.php

# Теперь регистрируем все сервис-провайдеры (включая ArtisanServiceProvider) через package:discover
RUN php artisan package:discover

# Теперь команда key:generate уже доступна – генерируем ключ
RUN php artisan key:generate --force


## Остальные скрипты composer (если есть ещё какие-то)
#RUN composer run-script post-autoload-dump || true

# Включаем Passport обратно
RUN sed -i '/\/\/Laravel\\Passport\\PassportServiceProvider::class,/s/^\/\///' config/app.php




# Переключаемся на пользователя sail (для безопасности)
#USER sail

EXPOSE 8000

# ВСТАВЬТЕ ЭТОТ ENTRYPOINT (исправляет генерацию ключей и Passport)
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
