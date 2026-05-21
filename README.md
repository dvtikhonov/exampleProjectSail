# exampleProjectSail

Монорепозиторий с несколькими Laravel-сервисами и единым Nginx/OpenResty gateway. Проект рассчитан на запуск через корневой `docker-compose.yml`.

## Состав проекта

- `main-app` - основное Laravel-приложение: Laravel 13, Breeze, Inertia/Vue, Tailwind CSS, Passport, веб-авторизация и проверка токенов для gateway.
- `service-a` - Laravel API-сервис с маршрутами `/api/pingS`, `/api/ping`, `/api/sales-outlets` и обновлением головной организации торговой точки.
- `service-b` - Laravel API-сервис с примером доверенной авторизации через заголовок `X-User-Id`, который выставляет gateway.
- `nginx-gateway` - единая точка входа на OpenResty/Nginx. Проксирует запросы в сервисы и проверяет Bearer-токен через `main-app`.
- `docker-compose.yml` - основной compose-файл для запуска всей системы.
- `main-app/compose.yaml` - отдельный Laravel Sail compose для изолированного запуска `main-app`; для общего запуска проекта обычно не нужен.

## Требования

- Docker и Docker Compose.
- WSL/Linux shell или PowerShell с доступом к Docker.
- Внешний MySQL, доступный контейнерам.

Локальный PowerShell может не видеть `php`, поэтому PHP/Artisan-команды выполняйте внутри контейнеров через `docker compose exec`.

## Первый запуск

1. Проверьте настройки подключения к MySQL.

   В корневом `docker-compose.yml` для `main-app` уже задано подключение к MySQL на хосте:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=host.docker.internal
   DB_PORT=3306
   ```

   Для `service-a` и `service-b` задайте свои `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` через `environment` в `docker-compose.yml` или через `.env` конкретного сервиса. Если сервисы используют разные базы, создайте их заранее во внешнем MySQL.

2. Соберите и запустите контейнеры.

   ```bash
   docker compose up -d --build
   ```

3. Сгенерируйте ключи приложений, если `.env` сервисов ещё пустые.

   `main-app` создаёт `.env` из `.env.example` при сборке образа и при старте через `entrypoint.sh` генерирует `APP_KEY` и Passport-ключи, если они отсутствуют.

   ```bash
   docker compose exec service-a php artisan key:generate
   docker compose exec service-b php artisan key:generate
   ```

4. Запустите миграции.

   ```bash
   docker compose exec main-app php artisan migrate
   docker compose exec service-a php artisan migrate
   docker compose exec service-b php artisan migrate
   ```

## Запуск и обслуживание

Обычный запуск после первичной настройки:

```bash
docker compose up -d
```

Запуск с пересборкой образов:

```bash
docker compose up -d --build
```

Просмотр логов:

```bash
docker compose logs -f
docker compose logs -f main-app
docker compose logs -f service-a
docker compose logs -f service-b
docker compose logs -f gateway
```

Остановка:

```bash
docker compose down
```

Пересборка одного сервиса:

```bash
docker compose build main-app
docker compose up -d main-app
```

## Адреса

- Gateway: `http://localhost:8080`
- `main-app` напрямую: `http://localhost`
- `service-a` напрямую: `http://localhost:8081`
- `service-b` напрямую: `http://localhost:8082`
- Vite dev server: `http://localhost:5173`
- MailHog: `http://localhost:8025`
- Redis: `localhost:6379`

Через gateway:

- `main-app`: `http://localhost:8080/`
- `service-a`: `http://localhost:8080/api/a/...`
- `service-b`: `http://localhost:8080/api/b/...`

Gateway переписывает префиксы `/api/a/` и `/api/b/` в `/api/` перед проксированием в соответствующий сервис.

## Авторизация через gateway

`nginx-gateway` использует `auth_request /auth-internal` и проверяет Bearer-токен через endpoint `main-app`:

```text
/api/auth/verify
```

`main-app` проверяет JWT Passport-токен, ищет его по `jti` в таблице Passport-токенов и возвращает `X-User-Id` при успешной проверке. Gateway передаёт этот заголовок дальше в сервисы.

Открытые маршруты gateway:

- `/login`
- `/register`
- `/oauth/token`

Защищённые маршруты должны вызываться с заголовком:

```http
Authorization: Bearer <token>
```

В `main-app` после web-login создаётся Passport-токен и сохраняется в сессии. Текущий токен можно получить через:

```text
/get-api-token
```

## Frontend `main-app`

`main-app` использует Breeze UI на Inertia/Vue, Vite и Tailwind CSS.

Команды frontend нужно запускать внутри контейнера `main-app`:

```bash
docker compose exec main-app npm install
docker compose exec main-app npm run dev
```

Production-сборка:

```bash
docker compose exec main-app npm install
docker compose exec main-app npm run build
```

Порт Vite `5173` проброшен в `docker-compose.yml`. В `main-app/.env.example` также указаны:

```env
VITE_DEV_SERVER_URL=http://localhost:5173
VITE_GATEWAY_ORIGIN=http://localhost:8080
```

## Полезные команды

Список маршрутов:

```bash
docker compose exec main-app php artisan route:list
docker compose exec service-a php artisan route:list
docker compose exec service-b php artisan route:list
```

Очистка кеша Laravel:

```bash
docker compose exec main-app php artisan optimize:clear
docker compose exec service-a php artisan optimize:clear
docker compose exec service-b php artisan optimize:clear
```

Запуск тестов:

```bash
docker compose exec main-app php artisan test
docker compose exec service-a php artisan test
docker compose exec service-b php artisan test
```

## База данных

Внутренний MySQL-контейнер в `docker-compose.yml` сейчас закомментирован. Проект использует внешний MySQL.

Для доступа к MySQL на хосте используется `host.docker.internal`. В Linux/WSL он пробрасывается через `extra_hosts` у `main-app` и `service-a`; для `service-b` при необходимости добавьте такой же `extra_hosts` или укажите другой адрес MySQL, доступный из контейнера.

Если сервис запускается вне корневого compose, проверьте его локальный `.env`: стандартные `.env.example` у сервисов по умолчанию настроены на SQLite и требуют актуализации под MySQL.

## Примечания

- `main-app` запускается в контейнере через Nginx + PHP-FPM + Supervisor на внутреннем порту `8000`.
- `service-a` и `service-b` запускаются через `php artisan serve` на внутреннем порту `8000`.
- `nginx-gateway/auth.lua` сейчас не используется: директивы `access_by_lua_file` в `nginx-gateway/nginx.conf` закомментированы.
- `PASSPORT_CLIENT_SECRET` нужен только если gateway будет переведён на схему, где он сам использует OAuth client credentials. В текущей схеме `auth_request` secret не требуется для первого запуска.