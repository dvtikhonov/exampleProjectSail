# service-d — Vue SPA + Sanctum (задел под Яндекс.Карты)

Laravel 13 (PHP 8.4) и Vue 3 SPA с авторизацией через **Laravel Sanctum** (cookie + CSRF на том же origin). После входа — заставка-плейсхолдер «Карта торговых точек» (без SDK карт в MVP). Отдельная MySQL-база `service_d_db`.

| Документ | Назначение |
|---|---|
| [корневой README](../README.md) | Docker, gateway, CI/CD, общая инфраструктура |

Порт по умолчанию: **8084** (`SERVICE_D_PORT` в `docker-compose.yml`). Vite dev: **5175** (`SERVICE_D_VITE_PORT`).

## Маршрутизация

| Путь | Описание |
|---|---|
| `http://localhost:8084/` | Vue SPA (прямой доступ к контейнеру) |
| `http://yandexmaps.localhost:8080/` | Через nginx-gateway (host-based routing) |
| `https://yandexmaps.94-228-117-27.sslip.io/` | Production (host nginx → gateway → service-d) |
| `POST /api/register`, `POST /api/login` | Публичная регистрация и вход |
| `POST /api/logout`, `GET /api/user` | `auth:sanctum` |

**Важно:** service-d обслуживается **целиком на субдомене** `yandexmaps.*` — без префикса `/api/d/` и без `auth_request` gateway. Gateway маршрутизирует по заголовку `Host` (см. `nginx-gateway/nginx.conf`).

## Быстрый старт (локально)

```bash
cp service-d/.env.example service-d/.env
docker compose build service-d
docker compose up -d service-d gateway
```

Для доступа через gateway добавьте в `/etc/hosts` (Linux/WSL):

```text
127.0.0.1 yandexmaps.localhost
```

Откройте `http://yandexmaps.localhost:8080/` — форма входа; после login — заставка.

При первом запуске контейнер соберёт фронтенд, если нет `public/spa-build/manifest.json` (см. `docker-entrypoint.sh`).

### База данных

Отдельная БД (не `sail_db`):

| Окружение | База |
|---|---|
| local / production | `service_d_db` |
| тесты | `service_d_db_testing` |

Создайте базы во внешнем MySQL на хосте:

```sql
CREATE DATABASE service_d_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE service_d_db_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Миграции **изменяют схему БД** — выполняйте только после явного согласия:

```bash
docker compose exec service-d php artisan migrate
```

### Ключ приложения

Файл `.env` в контейнере должен быть доступен на запись (в `docker-compose.yml` без суффикса `:ro`). После первого `cp .env.example .env`:

```bash
docker compose exec service-d php artisan key:generate
```

Если том `.env` смонтирован только для чтения, сгенерируйте ключ на хосте:

```bash
KEY=$(docker compose exec -T service-d php artisan key:generate --show)
sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" service-d/.env
```

## Переменные окружения (Sanctum)

Ключевые поля в `service-d/.env`:

```env
APP_URL=http://localhost:8084
DB_DATABASE=service_d_db
SESSION_DRIVER=database
SANCTUM_STATEFUL_DOMAINS=localhost:8084,localhost,yandexmaps.localhost,yandexmaps.localhost:8080,__SANCTUM_CURRENT_REQUEST_HOST__
```

Через gateway локально нужен порт в домене (`yandexmaps.localhost:8080`) — Origin браузера включает `:8080`. Плейсхолдер `__SANCTUM_CURRENT_REQUEST_HOST__` подставляет `Host` запроса динамически.

**Production** (после `scripts/vps-nginx-ssl.sh`; основной домен VPS — `94-228-117-27.sslip.io`):

```env
APP_URL=https://yandexmaps.94-228-117-27.sslip.io
SANCTUM_STATEFUL_DOMAINS=yandexmaps.94-228-117-27.sslip.io
SESSION_DOMAIN=yandexmaps.94-228-117-27.sslip.io
```

`APP_URL` — с `https://`. **`SESSION_DOMAIN` и `SANCTUM_STATEFUL_DOMAINS` — только hostname, без схемы** (не `https://...`, иначе cookie сессии не сохранится).

`SESSION_DOMAIN` можно не задавать — подставится из Host запроса.

## Frontend

Стек: Vue 3 (`<script setup>`), Vue Router, axios (`withCredentials: true`), Tailwind CSS 4.

```bash
docker compose exec service-d npm install
docker compose exec service-d npm run dev
```

Production-сборка:

```bash
docker compose exec service-d npm run build
```

Исходники SPA: `resources/js/spa-app/` (`Login.vue`, `Splash.vue`, composable `useAuth.js`).

## Тесты

Используется только `service_d_db_testing` (см. `service-d/.env.testing` и `scripts/test-services.sh`):

```bash
./scripts/test-services.sh service-d
```

## Production: SSL и субдомен

1. **DNS:** для sslip.io A-запись не нужна; субдомен `yandexmaps.94-228-117-27.sslip.io` резолвится на тот же IP, что и `94-228-117-27.sslip.io`.
2. На VPS из корня репозитория:

```bash
export COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml
export VPS_DOMAIN=94-228-117-27.sslip.io
export CERTBOT_EMAIL=you@example.com
docker compose up -d
./scripts/vps-nginx-ssl.sh all
```

Скрипт `scripts/vps-nginx-ssl.sh`:
- выпускает сертификат Let's Encrypt на `${VPS_DOMAIN}` и `yandexmaps.${VPS_DOMAIN}`;
- настраивает host nginx: оба домена проксируются на `127.0.0.1:8080` (Docker gateway);
- gateway по `Host: yandexmaps.*` направляет трафик в service-d.

Если сертификат для основного домена уже был выпущен ранее без субдомена:

```bash
./scripts/vps-nginx-ssl.sh issue-cert-maps
./scripts/vps-nginx-ssl.sh apply-nginx
```

Переменные скрипта:

| Переменная | По умолчанию | Описание |
|---|---|---|
| `YANDEXMAPS_SUBDOMAIN` | `yandexmaps` | Префикс субдомена |
| `YANDEXMAPS_DOMAIN` | `${YANDEXMAPS_SUBDOMAIN}.${VPS_DOMAIN}` | Полное имя для certbot и nginx |

После `apply-nginx` обновите `service-d/.env` (см. вывод скрипта и раздел **Переменные окружения** выше).

Деплой через GitHub Actions: `.github/workflows/deploy.yml` (параметр `run_migrations` для миграций service-d).

## Что не входит в MVP

- Яндекс.Карты (SDK, API key)
- Прокси торговых точек из service-a
- Общие пользователи с main-app
