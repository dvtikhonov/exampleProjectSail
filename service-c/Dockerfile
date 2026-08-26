# service-c/Dockerfile — build context: корень репозитория (см. docker-compose.yml)
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    curl \
    ca-certificates \
    gnupg \
    nginx \
    supervisor \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Лимиты multipart для фото блюд (согласованы с DishPhotoAllowedExtensions::MAX_SIZE_*).
COPY service-c/docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY service-c/composer.json service-c/composer.lock ./
COPY shared /var/www/shared

RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress --no-scripts

COPY service-c/package.json service-c/.npmrc ./
RUN npm install --ignore-scripts

COPY service-c .

RUN mkdir -p storage/app/public/dishes storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

RUN composer run-script post-autoload-dump

RUN npm run build

# Nginx + PHP-FPM (порт 8000) вместо однопоточного `php artisan serve`.
# zzz-*.conf — после zz-docker.conf, чтобы listen/pm не перетёрлись дефолтом образа.
COPY service-c/nginx.conf /etc/nginx/nginx.conf
COPY service-c/default.conf /etc/nginx/conf.d/default.conf
COPY service-c/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-www.conf
COPY service-c/supervisord.conf /etc/supervisord.conf

RUN rm -f /etc/nginx/sites-enabled/default \
    && mkdir -p /var/lib/nginx/body /var/lib/nginx/fastcgi /var/lib/nginx/proxy \
        /var/lib/nginx/scgi /var/lib/nginx/uwsgi /run/nginx \
    && chown -R www-data:www-data /var/lib/nginx /run/nginx

COPY service-c/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000 5174
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
