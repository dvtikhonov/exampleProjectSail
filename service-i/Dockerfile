# service-i/Dockerfile — multi-stage: Node только для Vite build, runtime без Node.
# Build context: корень репозитория (см. docker-compose.yml).

# ----- stage: frontend assets -----
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY service-i/package.json service-i/package-lock.json service-i/.npmrc ./
RUN npm ci

COPY service-i/vite.config.js ./
COPY service-i/resources ./resources
COPY service-i/public ./public

RUN npm run build

# ----- stage: PHP runtime (без Node) -----
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libsqlite3-dev \
    curl \
    ca-certificates \
    && docker-php-ext-install zip pdo_sqlite intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY service-i/composer.json service-i/composer.lock ./

# Без --prefer-dist: при HTTP 400 на codeload.github.com Composer может клонировать из git.
RUN for attempt in 1 2 3; do \
      composer install --no-interaction --optimize-autoloader --no-progress --no-scripts --no-dev \
      && break; \
      if [ "$attempt" = 3 ]; then exit 1; fi; \
      sleep 10; \
    done

COPY service-i .

# Готовые Vite-ассеты из frontend-stage (Node в runtime не нужен).
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

RUN composer run-script post-autoload-dump

COPY service-i/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
