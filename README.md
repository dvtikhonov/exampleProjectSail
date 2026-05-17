# exampleProjectSail

Монорепозиторий с несколькими Laravel-сервисами и единым Nginx/OpenResty gateway.

## Состав

- `main-app` - основное Laravel-приложение с Laravel Breeze, Inertia/Vue, авторизацией и Passport.
- `service-a` - отдельный Laravel-сервис.
- `service-b` - отдельный Laravel-сервис.
- `nginx-gateway` - единая точка входа, проксирует запросы в сервисы и проверяет авторизацию через `main-app`.
- `docker-compose.yml` - запуск всей системы.

## Требования

- Docker
- Docker Compose
- WSL/Linux shell или PowerShell с доступом к Docker
- Node.js и npm, если frontend `main-app` запускается или собирается вне Docker

## Laravel Breeze

В `main-app` используется Laravel Breeze с Inertia/Vue. Breeze отвечает за базовые auth-страницы и пользовательские сценарии: login, register, password reset, email verification, dashboard и profile.

Связанные зависимости находятся в `main-app/composer.json` и `main-app/package.json`: `laravel/breeze`, `inertiajs/inertia-laravel`, `@inertiajs/vue3`, `vue`, `vite`, `tailwindcss` и `@tailwindcss/forms`.

## Первый запуск

`main-app` сам создаёт `.env` из `.env.example` во время сборки образа, если файла ещё нет. Вручную копировать `.env.example` для `main-app` не нужно.

Перед запуском проверьте подключение к внешнему MySQL. Все сервисы должны использовать один внешний MySQL-сервер, но могут работать с разными базами данных:

```env
DB_CONNECTION=mysql
DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=main_db
DB_USERNAME=root
DB_PASSWORD=root
```

DB-переменные можно задавать через `environment` в `docker-compose.yml` или через `.env` конкретного сервиса. Для `service-a` и `service-b` укажите свои значения `DB_DATABASE`, если у каждого сервиса отдельная база.

Соберите и запустите все контейнеры:

```bash
docker compose up -d --build
```

`main-app` при старте сам генерирует `APP_KEY` и Passport-ключи через `entrypoint.sh`, если они отсутствуют.

Для `service-a` и `service-b` сгенерируйте `APP_KEY`, если их `.env` ещё пустые:

```bash
docker compose exec service-a php artisan key:generate
docker compose exec service-b php artisan key:generate
```

Запустите миграции:

```bash
docker compose exec main-app php artisan migrate
docker compose exec service-a php artisan migrate
docker compose exec service-b php artisan migrate
```

## Запуск

Обычный запуск после первичной настройки:

```bash
docker compose up -d
```

Запуск с пересборкой образов:

```bash
docker compose up -d --build
```

Просмотр логов всех сервисов:

```bash
docker compose logs -f
```

Просмотр логов одного сервиса:

```bash
docker compose logs -f main-app
docker compose logs -f service-a
docker compose logs -f service-b
docker compose logs -f gateway
```

Остановка:

```bash
docker compose down
```

## Адреса

- Gateway: `http://localhost:8080`
- Main app напрямую: `http://localhost`
- Service A напрямую: `http://localhost:8081`
- Service B напрямую: `http://localhost:8082`
- MailHog: `http://localhost:8025`
- Redis: `localhost:6379`

Через gateway сервисы доступны так:

- `main-app`: `http://localhost:8080/`
- `service-a`: `http://localhost:8080/api/a/...`
- `service-b`: `http://localhost:8080/api/b/...`

## Полезные команды

Выполнить artisan-команду:

```bash
docker compose exec main-app php artisan route:list
docker compose exec service-a php artisan route:list
docker compose exec service-b php artisan route:list
```

Очистить кеш Laravel:

```bash
docker compose exec main-app php artisan optimize:clear
docker compose exec service-a php artisan optimize:clear
docker compose exec service-b php artisan optimize:clear
```

Запустить тесты:

```bash
docker compose exec main-app php artisan test
docker compose exec service-a php artisan test
docker compose exec service-b php artisan test
```

Пересобрать один сервис:

```bash
docker compose build main-app
docker compose up -d main-app
```

## Frontend main-app

`main-app` содержит Breeze UI на Inertia/Vue и Vite. Если нужно запускать Vite отдельно на хосте:

```bash
cd main-app
npm install
npm run dev
```

Для production-сборки:

```bash
cd main-app
npm install
npm run build
```

## База данных

В проекте используется внешний MySQL, доступный всем сервисам. В `docker-compose.yml` для `main-app` уже задано подключение к MySQL на хосте:

```env
DB_CONNECTION=mysql
DB_HOST=host.docker.internal
DB_PORT=3306
```

Если Docker запущен в Linux/WSL, `host.docker.internal` пробрасывается через `extra_hosts` у `main-app`. Для `service-a` и `service-b` при необходимости добавьте такой же `extra_hosts` или укажите доступный из контейнера адрес MySQL.

Внутренний MySQL-контейнер в `docker-compose.yml` не используется и оставлен закомментированным.

`main-app/Dockerfile` создаёт `.env` из `.env.example` при сборке образа. Для остальных сервисов DB-настройки задаются либо в `docker-compose.yml`, либо через их локальные `.env`, если они нужны для запуска вне Compose.

## Примечания

- Корневой `docker-compose.yml` предназначен для запуска всей системы.
- `main-app/compose.yaml` - отдельный Sail compose только для `main-app`.
- Gateway проверяет авторизацию через `main-app` endpoint `/api/auth/verify`.
- `nginx-gateway/auth.lua` сейчас не используется: строки `access_by_lua_file` в `nginx-gateway/nginx.conf` закомментированы. Файл оставлен как заготовка для альтернативной проверки токенов через Lua/OpenResty, если она понадобится позже.
- `PASSPORT_CLIENT_SECRET` нужен только если gateway будет переведён на схему, где он сам использует OAuth client credentials. В текущей схеме `auth_request` этот secret не требуется для первого запуска.