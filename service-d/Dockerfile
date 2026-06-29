# service-d/Dockerfile — build context: корень репозитория (см. docker-compose.yml)
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    curl \
    ca-certificates \
    gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install zip pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY service-d/composer.json service-d/composer.lock ./

# Без --prefer-dist: при HTTP 400 на codeload.github.com Composer может клонировать из git.
RUN for attempt in 1 2 3; do \
      composer install --no-interaction --optimize-autoloader --no-progress --no-scripts --no-dev \
      && break; \
      if [ "$attempt" = 3 ]; then exit 1; fi; \
      sleep 10; \
    done

COPY service-d/package.json service-d/.npmrc ./
RUN npm install --ignore-scripts

COPY service-d .

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

RUN composer run-script post-autoload-dump

RUN npm run build

COPY service-d/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000 5175
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
