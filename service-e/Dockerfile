# service-e/Dockerfile — build context: корень репозитория (см. docker-compose.yml)
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY service-e/composer.json service-e/composer.lock ./
COPY shared /var/www/shared

RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress --no-dev --no-scripts

COPY service-e .

RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

# post-install scripts (cache:clear) require application source (e.g. src/Controller/)
RUN APP_ENV=prod APP_DEBUG=0 composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress --no-dev

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
