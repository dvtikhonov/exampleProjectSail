# exampleProjectSail

Монорепозиторий с несколькими Laravel-сервисами и единым Nginx/OpenResty gateway. Основной сценарий запуска — через корневой `docker-compose.yml`.

## Состав проекта

| Каталог / файл | Назначение |
|---|---|
| `main-app` | Основное Laravel-приложение: Laravel 13, Breeze, Inertia/Vue, Tailwind CSS, Passport, Laravel Reverb (Echo), веб-авторизация и проверка токенов для gateway |
| `service-a` | Laravel API: торговые точки (`/api/sales-outlets`) |
| `service-e` | Symfony 8 API: торговые точки через `/api/e/` (общая таблица `sales_outlets` с `service-a`) |
| `service-b` | Laravel API: единый Report API (Strategy: `csv_download`, `html_email`, `max_message`), очередь `BuildSalesOutletsReportJob`, REST-статистика и live-updates в Reverb через domain events + listeners |
| `service-b-queue` | Worker очереди `service-b` (`queue:work`) для фоновых отчётов |
| `service-c` | Laravel + Vue 3: MAX mini-app «Заказ еды», webhook MAX, UI Stand |
| `service-d` | Laravel + Vue 3 SPA: Sanctum на субдомене `yandexmaps.*`, привязка организации Яндекс.Карт и синхронизация отзывов (`yandex-parser`, `service-d-queue`) |
| `reverb` | WebSocket-сервер Laravel Reverb (образ `main-app`), порт `8090` |
| `shared/sales-outlets-domain` | Локальный Composer-пакет с общей доменной частью торговых точек |
| `nginx-gateway` | Единая точка входа: проксирование, `auth_request`, CORS для `/api/a/`, `/api/b/` и `/api/e/` |
| `docker-compose.yml` | Основной compose-файл для локального запуска |
| `docker-compose.ci.yml` | Overlay для CI: внутренний MySQL и `depends_on` с healthcheck |
| `scripts/` | Вспомогательные скрипты: тесты, VPS, MAX-туннели, диагностика — см. [docs/scripts.md](docs/scripts.md) |
| `.github/workflows/ci.yml` | CI: Pint, сборка frontend, Docker-тесты |
| `.github/workflows/deploy.yml` | CD: деплой на VPS по SSH |
| `main-app/compose.yaml` | Отдельный Laravel Sail compose для изолированного запуска `main-app`; для общего запуска обычно не используется |

## Архитектура запросов

```mermaid
flowchart LR
  Browser --> Gateway
  Gateway -->|auth_request| MainApp
  Gateway --> ServiceA
  Gateway --> ServiceB
  Browser -->|сессия / Inertia| MainApp
  MainApp -->|MicroserviceHttpClient| Gateway
  Gateway --> ServiceA
  Gateway --> ServiceB
  ServiceBQueue -->|create / updateStatus| ServiceB
  ServiceB -->|SalesOutletReportJobMutated| Listeners
  Listeners -->|ReportJobStatsChanged| Reverb
  Browser -->|Echo private channel| Reverb
  MainApp -->|/broadcasting/auth| Reverb
```

- **Торговые точки (CRUD, фильтры):** браузер → `VITE_GATEWAY_ORIGIN` → `service-a` (Bearer + CORS на gateway).
- **Экспорт, почта, MAX, статистика отчётов:** браузер → `main-app` (web-сессия, `auth.passport`) → gateway → `service-b` (`MicroserviceHttpClient`).
- **Live-статистика отчётов:** после мутации задачи `service-b` диспатчит `SalesOutletReportJobMutated` → listener `BroadcastReportJobStatsOnJobMutation` → broadcast `ReportJobStatsChanged` в Reverb; `main-app` подписывается через Laravel Echo на private-канал `report-jobs.stats` (авторизация — `POST /broadcasting/auth`).

## Требования

- Docker и Docker Compose.
- PHP **8.3+** в контейнерах (CI — PHP 8.4).
- WSL/Linux shell или PowerShell с доступом к Docker.
- Внешний MySQL, доступный контейнерам (для локальной разработки).

Локальный PowerShell может не видеть `php`, поэтому PHP/Artisan/Composer/npm-команды выполняйте внутри контейнеров через `docker compose exec` или через WSL.

## Первый запуск

### 1. Настройка MySQL

В корневом `docker-compose.yml` для `main-app` уже задано подключение к MySQL на хосте:

```env
DB_CONNECTION=mysql
DB_HOST=host.docker.internal
DB_PORT=3306
```

Для `service-b` и `service-b-queue` подключение задаётся через переменные окружения с дефолтами:

```env
SERVICE_B_DB_HOST=host.docker.internal
SERVICE_B_DB_PORT=3306
SERVICE_B_DB_DATABASE=sail_db
SERVICE_B_DB_USERNAME=root
SERVICE_B_DB_PASSWORD=<your-local-password>
```

Для `service-d` — отдельная база (не `sail_db`):

```env
SERVICE_D_DB_HOST=host.docker.internal
SERVICE_D_DB_DATABASE=service_d_db
SERVICE_D_DB_PASSWORD=<your-local-password>
```

Создайте `service_d_db` и `service_d_db_testing` во внешнем MySQL (см. [service-d/README.md](service-d/README.md)).

Для `service-a` задайте `DB_*` через `environment` в `docker-compose.yml` или через `.env` сервиса. Если сервисы используют разные базы, создайте их заранее во внешнем MySQL.

Стандартные `.env.example` у сервисов по умолчанию настроены на SQLite — для Docker-запуска через корневой compose их нужно перевести на MySQL.

### 2. Сборка и запуск

```bash
docker compose up -d --build
```

Поднимаются `main-app`, `service-a`, `service-b`, `service-b-queue`, `service-c`, `service-d`, `service-e`, `reverb`, `redis`, `mailhog`, `gateway`.

### 3. Ключи приложений

`main-app` создаёт `.env` из `.env.example` при сборке образа; при старте `entrypoint.sh` генерирует `APP_KEY` и Passport-ключи, если они отсутствуют.

Для `service-a` и `service-b` при пустом `.env`:

```bash
docker compose exec service-a php artisan key:generate
docker compose exec service-b php artisan key:generate
docker compose exec service-d php artisan key:generate
```

### 4. Миграции

Миграции затрагивают базы сервисов. Перед запуском проверьте `.env` / `environment` и убедитесь, что команда выполняется для нужной БД.

```bash
docker compose exec main-app php artisan migrate
docker compose exec service-a php artisan migrate
docker compose exec service-b php artisan migrate
docker compose exec service-d php artisan migrate
```

Для отчётов `service-b` нужны worker и Reverb (в compose уже описаны):

```bash
docker compose up -d service-b-queue reverb
```

Письма `html_email` в dev перехватывает MailHog (`http://localhost:8025`). Отчёты `max_message` уходят в [MAX Bot API](https://dev.max.ru/docs-api) — токен и получатели только в `.env` `service-b` (см. [service-b/README.md](service-b/README.md)).

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
docker compose logs -f service-b-queue
docker compose logs -f reverb
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

| Сервис | URL |
|---|---|
| Gateway | `http://localhost:8080` |
| `main-app` напрямую | `http://localhost` (порт `MAIN_APP_PORT`, по умолчанию `80`) |
| `service-a` напрямую | `http://localhost:8081` |
| `service-b` напрямую | `http://localhost:8082` |
| `service-c` напрямую | `http://localhost:8083` |
| `service-d` напрямую | `http://localhost:8084` |
| `service-e` напрямую | `http://localhost:8086` |
| `service-d` через gateway (субдомен) | `http://yandexmaps.localhost:8080` (нужна запись в `/etc/hosts`) |
| Laravel Reverb (WebSocket) | `ws://localhost:8090` (порт `REVERB_EXTERNAL_PORT`) |
| Vite dev server | `http://localhost:5173` (`main-app`), `5174` (`service-c`), `5175` (`service-d`) |
| MailHog | `http://localhost:8025` |
| Redis | `localhost:6379` |

Через gateway:

- `main-app`: `http://localhost:8080/`
- `service-a`: `http://localhost:8080/api/a/...`
- `service-b`: `http://localhost:8080/api/b/...`
- `service-e`: `http://localhost:8080/api/e/...`

Gateway переписывает префиксы `/api/a/`, `/api/b/` и `/api/e/` в `/api/` перед проксированием в соответствующий сервис.

Для `/api/a/...` и `/api/b/...` в gateway настроен CORS:

- `OPTIONS` preflight обрабатывается на стороне `nginx-gateway` и возвращает `204`.
- Разрешены методы `GET, POST, PUT, PATCH, DELETE, OPTIONS`.
- Разрешены заголовки `Authorization, Content-Type, Accept`.
- `Access-Control-Allow-Origin` берётся из `$http_origin`, заголовок `Vary: Origin` добавляется всегда.
- CORS-заголовки от backend-сервисов скрываются через `proxy_hide_header`, чтобы gateway был единственной точкой управления CORS.

Браузерные запросы к `service-a` из Vue идут напрямую через gateway, например:

```text
PATCH ${VITE_GATEWAY_ORIGIN}/api/a/sales-outlets/{rowId}
POST  ${VITE_GATEWAY_ORIGIN}/api/a/sales-outlets/{rowId}/head-organization
```

Эти вызовы не проходят через Laravel-контроллеры `main-app`: браузер обращается к `service-a` через `nginx-gateway`, поэтому preflight и заголовок `Authorization` должны корректно обрабатываться gateway.

## Основные маршруты

### `main-app`

Web (Inertia):

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/` | Стартовая страница |
| `GET` | `/dashboard` | Dashboard после web-авторизации |
| `GET` | `/objects-sales-outlets` | Страница объектов торговых точек |
| `GET` | `/objects-sales-outlets-2` | Альтернативная тёмная страница (экспорт, почта, MAX, live-статистика) |
| `GET` | `/objects-sales-outlets-3` | Тёмная страница торговых точек на backend `service-e` (без export/mail/max/stats) |
| `POST` | `/objects-sales-outlets-2/export` | Создание CSV-экспорта (прокси в `service-b`) |
| `GET` | `/objects-sales-outlets-2/export/{uuid}` | Статус экспорта |
| `GET` | `/objects-sales-outlets-2/export/{uuid}/download` | Скачивание экспорта |
| `POST` | `/objects-sales-outlets-2/mail` | Создание отчёта на email |
| `GET` | `/objects-sales-outlets-2/mail/{uuid}` | Статус email-отчёта |
| `POST` | `/objects-sales-outlets-2/max` | Создание отчёта в MAX (`report_type=max_message`) |
| `GET` | `/objects-sales-outlets-2/max/{uuid}` | Статус отчёта в MAX |
| `GET` | `/objects-sales-outlets-2/reports/stats` | Агрегированная статистика задач отчётов |
| `GET` | `/get-api-token` | Passport-токен из сессии |
| `GET/PATCH/DELETE` | `/profile` | Профиль пользователя (Breeze) |

Страницы объектов и профиля защищены middleware `auth.passport`: для web-запросов токен берётся из сессии, при отсутствии — редирект на login.

API:

| Метод | Путь | Описание |
|---|---|---|
| `GET\|POST` | `/api/auth/verify` | Проверка Bearer-токена для gateway (`X-User-Id`) |
| `POST` | `/api/auth/check` | Внутренняя проверка токена (исключена из CSRF) |

Broadcasting (web + `AuthenticateBroadcastingPassport`):

| Метод | Путь | Описание |
|---|---|---|
| `POST` | `/broadcasting/auth` | Авторизация подписки Echo на `private-report-jobs.stats` |

### `service-a`

Напрямую (`/api/...`):

| Метод | Путь | Auth |
|---|---|---|
| `GET` | `/sales-outlets` | `trust.gateway` |
| `PATCH` | `/sales-outlets/{salesOutlet}` | `trust.gateway` |
| `POST` | `/sales-outlets/{salesOutlet}/head-organization` | `trust.gateway` |
| `DELETE` | `/sales-outlets/{salesOutlet}` | `trust.gateway` |

Через gateway — те же маршруты с префиксом `/api/a`, например `GET /api/a/sales-outlets`.

### `service-b`

Напрямую (`/api/...`):

| Метод | Путь | Auth |
|---|---|---|
| `GET` | `/data` | `trust.gateway` (только `local` / `testing`) |
| `GET` | `/sales-outlets/reports/stats` | `trust.gateway` |
| `POST` | `/sales-outlets/reports` | `trust.gateway` |
| `GET` | `/sales-outlets/reports/{uuid}` | `trust.gateway` |
| `GET` | `/sales-outlets/reports/{uuid}/download` | `trust.gateway` |

Через gateway — префикс `/api/b`, например `POST /api/b/sales-outlets/reports`.

Тело `POST /sales-outlets/reports` включает `report_type`:

- `csv_download` — CSV-файл для скачивания;
- `html_email` — HTML-отчёт на email (в dev — MailHog);
- `max_message` — HTML-таблица в мессенджер MAX ([POST /messages](https://dev.max.ru/docs-api/methods/POST/messages), лимит текста 4000 символов).

Ответ `GET /sales-outlets/reports/stats` — JSON с полями `by_type` (счётчики `pending`, `processing`, `completed`, `failed`, `total` по каждому типу, включая `max_message`) и `generated_at`.

### Live-статистика отчётов: термины и поток

| Термин | Значение |
|---|---|
| **snapshot** | Начальный REST-снимок статистики (`by_type`, `generated_at`) |
| **broadcast** | Публикация в Reverb после мутации задачи: `SalesOutletReportJobMutated` → listener → `ReportJobStatsChanged` |
| **channel** | Private-канал `report-jobs.stats` (`Echo.private('report-jobs.stats')`, wire-имя `private-report-jobs.stats`) |
| **event** | Broadcast-событие `ReportJobStatsChanged` (в Echo слушается как `.ReportJobStatsChanged`) |
| **payload** | JSON с полями `by_type` и `generated_at` — одинаковый формат для snapshot и event |

**Backend (`service-b`):**

Цепочка из трёх уровней (persistence не вызывает Reverb напрямую):

```text
EloquentSalesOutletsReportJobRepository (create / updateStatus)
    → SalesOutletReportJobMutated
        → BroadcastReportJobStatsOnJobMutation → ReportJobStatsChanged → Reverb
        → LogSalesOutletReportJobMutation → PSR-3 log (audit)
```

- после `create()` / `updateStatus()` репозиторий диспатчит domain event `SalesOutletReportJobMutated` через `EventDispatcherInterface`;
- `BroadcastReportJobStatsOnJobMutation` вызывает `SalesOutletsReportStatsBroadcaster::broadcastCurrentStats()`;
- broadcaster собирает payload через `SalesOutletsReportStatsRepositoryInterface::aggregate()` и диспатчит `ReportJobStatsChanged` в `PrivateChannel('report-jobs.stats')`;
- `findByUuid()` событие **не** диспатчит — только мутации.

Ключевые файлы: `service-b/app/Events/SalesOutletReportJobMutated.php`, `app/Listeners/BroadcastReportJobStatsOnJobMutation.php`, `app/Repositories/SalesOutlets/EloquentSalesOutletsReportStatsRepository.php`.

Подробнее (Strategy, контракты, расширение через listeners): [service-b/README.md](service-b/README.md).

**Frontend (`main-app`, страница `/objects-sales-outlets-2`):**

1. Загружается **snapshot**: `GET /objects-sales-outlets-2/reports/stats` → прокси в `service-b` (`/api/b/sales-outlets/reports/stats`).
2. Подписка на **channel** через Laravel Echo: `Echo.private('report-jobs.stats')`, авторизация — `POST /broadcasting/auth`.
3. При создании/смене статуса задачи `service-b` отправляет **broadcast** **event** `ReportJobStatsChanged`.
4. Composable `useReportJobStats` (`resources/js/Composables/useReportJobStats.js`) получает **event** и применяет **payload** к UI без перезагрузки страницы (типы: CSV, Почта, MAX).
5. Кнопка **«Отправить в MAX»** в `DarkSalesOutletsToolbar` → `POST /objects-sales-outlets-2/max` с теми же фильтрами/колонками, что у экспорта и почты; статус — polling `GET /objects-sales-outlets-2/max/{uuid}` каждые 2 с; при `failed` показывается `error_message` из API (без технических деталей токена).

## Авторизация через gateway

`nginx-gateway` использует `auth_request /auth-internal` и проверяет Bearer-токен через endpoint `main-app` **только для микросервисов** (`/api/a/`, `/api/b/`, `/api/c/`):

```text
/api/auth/verify
```

Веб-страницы `main-app` (`/`, `/login`, `/dashboard`, `/objects-sales-outlets-2`, …) проксируются **без** `auth_request`: доступ контролирует Laravel (сессия + middleware `auth` / `auth.passport`). Это совпадает с локальной разработкой, где UI открывается на `:80`, а gateway (`:8080`) используется для API.

`main-app` разбирает JWT Passport-токен, ищет его по `jti` в таблице Passport-токенов и возвращает заголовок `X-User-Id` при успешной проверке. Gateway передаёт этот заголовок дальше в сервисы.

Результаты успешной проверки кэшируются в nginx на 60 секунд (`proxy_cache auth_cache`). Заголовок ответа `X-Auth-Cache` показывает статус кэша (`HIT`, `MISS`, `BYPASS`).

Маршруты gateway с `auth_request` (требуют `Authorization: Bearer <token>`):

- `/api/a/...`
- `/api/b/...`
- `/api/c/...` (кроме webhook MAX)

Остальные пути через gateway идут в `main-app` без проверки Bearer на уровне nginx.

В `main-app` после web-login создаётся Passport-токен и сохраняется в сессии. Текущий токен:

```text
GET /get-api-token
```

`service-a` и `service-b` доверяют gateway через middleware `trust.gateway`: сервисы ожидают заголовок `X-User-Id`, который gateway получает от `main-app` после проверки токена.

Серверные вызовы из `main-app` в микросервисы идут на `http://gateway/api/a` и `http://gateway/api/b` (см. `config/services.php`), с Bearer-токеном и при необходимости `X-User-Id`.

## Frontend `main-app`

`main-app` использует Breeze UI на Inertia/Vue, Vite, Tailwind CSS и Laravel Echo (Reverb).

Команды frontend запускайте внутри контейнера `main-app`:

```bash
docker compose exec main-app npm install
docker compose exec main-app npm run dev
```

Production-сборка:

```bash
docker compose exec main-app npm install
docker compose exec main-app npm run build
```

Порт Vite `5173` проброшен в `docker-compose.yml`. В `main-app/.env.example`:

```env
VITE_DEV_SERVER_URL=http://localhost:5173
VITE_GATEWAY_ORIGIN=http://localhost:8080
VITE_REVERB_APP_KEY=local-app-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8090
VITE_REVERB_SCHEME=http
```

HTTP-вызовы к `service-a` из Vue идут через `VITE_GATEWAY_ORIGIN` (см. `main-app/resources/js/Services/salesOutlets.js`).

Live-статистика на странице `/objects-sales-outlets-2` реализована в `useReportJobStats` (см. раздел **Live-статистика отчётов** выше и [service-b/README.md](service-b/README.md)).

### Диагностика live-статистики

| Симптом | Что проверить |
|---|---|
| Snapshot не загружается | Авторизация в `main-app`; `service-b` доступен; `GET /objects-sales-outlets-2/reports/stats` возвращает `200` |
| Snapshot есть, live-обновлений нет | Контейнеры `reverb` и `service-b-queue` запущены; в `service-b` — `BROADCAST_CONNECTION=reverb` |
| Echo не инициализируется | В `main-app/.env` задан `VITE_REVERB_APP_KEY`; после изменения `.env` — пересборка frontend (`npm run dev` / `npm run build`) |
| WebSocket не подключается | `VITE_REVERB_HOST=localhost`, `VITE_REVERB_PORT=8090`; порт `8090` проброшен (`REVERB_EXTERNAL_PORT`) |
| Подписка на channel отклоняется | `POST /broadcasting/auth` возвращает `200`; пользователь авторизован (web-сессия + Passport) |
| Event не приходит после экспорта | Работает `service-b-queue`; статус задачи меняется (`pending` → `processing` → `completed` / `failed`) |

Быстрая проверка инфраструктуры:

```bash
docker compose up -d reverb service-b service-b-queue
docker compose logs -f reverb service-b service-b-queue
```

E2E-проверка двух WebSocket-клиентов: раздел **E2E: статистика отчётов и WebSocket** ниже.

## Shared domain package

`shared/sales-outlets-domain` — локальный Composer-пакет `example/sales-outlets-domain` с namespace `Shared\SalesOutletsDomain\`. Содержит общие для `service-a` и `service-b` примитивы:

- enum `SalesOutletStatus`, `HeadOrganizationType`;
- DTO `SalesOutletRowDto`, `SalesOutletFilterDto`;
- метаданные колонок `SalesOutletColumns`;
- composable query-filters (`AbstractFilter/QueryFilters/*`);
- `SalesOutletQueryFilter`, `SalesOutletFilterFactory`, `FilterQuerySalesOutletComposite`.

Пакет подключён в `service-a/composer.json` и `service-b/composer.json` через Composer `path` repository. В Docker каталог `shared` монтируется в `/var/www/shared`.

```json
{
  "type": "path",
  "url": "../shared/sales-outlets-domain",
  "options": {
    "symlink": true
  }
}
```

После изменений в пакете:

```bash
docker compose exec service-a composer dump-autoload
docker compose exec service-b composer dump-autoload
```

При изменении версии или зависимостей:

```bash
docker compose exec service-a composer update example/sales-outlets-domain
docker compose exec service-b composer update example/sales-outlets-domain
```

Eloquent-модели, контроллеры, FormRequest, Report API (Strategy, jobs, domain/broadcast events) и миграции остаются внутри конкретных Laravel-сервисов.

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
docker compose exec service-b-queue php artisan optimize:clear
docker compose up -d --force-recreate service-b-queue
```

Запуск тестов одного сервиса:

```bash
docker compose exec main-app php artisan test
docker compose exec service-a php artisan test
docker compose exec service-b php artisan test
```

## E2E: статистика отчётов и WebSocket

E2E-проверка: два независимых WebSocket-клиента получают одинаковые события `ReportJobStatsChanged`, пока `service-b-queue` обрабатывает CSV- и email-отчёты.

Предварительно: запущен compose, есть пользователь с Passport-токеном, работают `reverb` и `service-b-queue`.

Рекомендуемый запуск (обёртка создаёт токен и проверяет `/broadcasting/auth`):

```bash
./scripts/e2e-verify-report-stats.sh
```

Ручной запуск `.cjs` (pusher-js из `main-app/node_modules`):

```bash
export TOKEN="<passport-bearer-token>"
export AUTH_BASE_URL=http://localhost
export MAIN_APP_URL=http://localhost
export SERVICE_B_URL=http://localhost:8082
export REVERB_HOST=localhost
export REVERB_PORT=8090

node scripts/e2e-verify-report-stats.cjs
```

## CI

Workflow `.github/workflows/ci.yml` запускается на `push` и `pull_request`.

| Job | Что проверяет |
|---|---|
| `php-style` | Laravel Pint (`--test`) для `main-app`, `service-a`, `service-b`, `service-c`, `service-d`; PHP-CS-Fixer (`@Symfony`) для `service-e` (PHP 8.4) |
| `frontend-build` | `npm ci` + `npm run build` в `main-app` и `service-d` (Node 22) |
| `backend-tests` | Docker Compose с overlay `docker-compose.ci.yml`, внутренний MySQL, `composer install` в сервисах, затем `./scripts/test-services.sh all` |

Локально воспроизвести CI-контур тестов:

```bash
export COMPOSE_FILE=docker-compose.yml:docker-compose.ci.yml
export TEST_DB_PASSWORD=12345678
export TEST_DB_HOST=mysql
export SERVICE_B_DB_HOST=mysql
export SERVICE_B_DB_DATABASE=sail_db_testing
export SERVICE_B_DB_PASSWORD=12345678

docker compose build main-app service-a service-b service-c service-d
docker compose run --rm --no-deps service-a composer install --no-interaction --prefer-dist --no-progress
docker compose run --rm --no-deps service-b composer install --no-interaction --prefer-dist --no-progress
docker compose run --rm --no-deps service-d composer install --no-interaction --prefer-dist --no-progress
docker compose up -d mysql redis main-app service-a service-b service-c service-d
./scripts/test-services.sh all
```

Полный каталог скриптов: [docs/scripts.md](docs/scripts.md).

## CD (деплой на VPS)

Workflow `.github/workflows/deploy.yml` — ручной запуск `workflow_dispatch`.

| Параметр | Описание |
|---|---|
| `deploy_ref` | Ветка или тег для деплоя (по умолчанию `main`) |
| `run_migrations` | Запускать миграции после деплоя (`false` по умолчанию) |

Этапы:

1. **`deploy-via-ssh`** (окружение GitHub `production`) — `git checkout`, `docker compose build`, `docker compose up -d --remove-orphans` с overlay `docker-compose.prod.yml`. При ошибке выполняется откат к предыдущему коммиту.
2. **`run-migrations`** (окружение `production-migrations`, только если `run_migrations=true`) — миграции во всех сервисах (`main-app`, `service-a`, `service-b`, `service-c`, `service-d`) через SSH.

На VPS порты **80/443** занимает системный nginx (Let's Encrypt). Docker gateway слушает только `127.0.0.1:8080`. Первичная настройка SSL: `scripts/vps-nginx-ssl.sh` — сертификат на основной домен и субдомен `yandexmaps.*` для service-d (см. [service-d/README.md](service-d/README.md)). Overlay: `docker-compose.prod.yml`.

**Production-домен VPS:** `94-228-117-27.sslip.io` (sslip.io — отдельная A-запись не нужна, домен привязан к IP `94.228.117.27`).

| Сервис | Production URL |
|---|---|
| `main-app` | `https://94-228-117-27.sslip.io/` |
| `service-c` (MAX mini-app) | `https://94-228-117-27.sslip.io/max-app` |
| `service-c` (webhook) | `https://94-228-117-27.sslip.io/api/webhooks/max` |
| `service-d` | `https://yandexmaps.94-228-117-27.sslip.io/` |
| phpMyAdmin (только после настройки) | `https://pma.94-228-117-27.sslip.io/` |

В `.env` на VPS: `main-app` → `APP_URL=https://94-228-117-27.sslip.io`; `REVERB_ALLOWED_ORIGINS=https://94-228-117-27.sslip.io` (см. `docker-compose.prod.yml`).

### phpMyAdmin на VPS

phpMyAdmin доступен **только в production** (`docker-compose.prod.yml`). В базовый `docker-compose.yml` для локальной разработки он **не** добавляется.

MySQL установлен **на хосте VPS** (не в Docker). Контейнер phpMyAdmin подключается к нему через `host.docker.internal:3306`. Три слоя защиты:

1. **Сеть** — phpMyAdmin слушает только `127.0.0.1:8085`; порт MySQL `3306` не открывается в UFW.
2. **Host nginx** — HTTPS (Let's Encrypt) + HTTP Basic Auth (`htpasswd`, файл вне репозитория).
3. **phpMyAdmin** — вход под `pma_admin` (не root), права только на прикладные БД `sail_db` и `service_d_db`.

```mermaid
flowchart LR
  Admin["Администратор"] -->|"HTTPS + Basic Auth"| PMA_Nginx["Host nginx\npma.*.sslip.io"]
  PMA_Nginx -->|"127.0.0.1:8085"| PMA["phpMyAdmin container"]
  PMA --> MySQLHost["MySQL host :3306"]
  Admin -->|"логин MySQL pma_admin"| PMA
```

#### Переменные окружения (корневой `.env` на VPS)

| Переменная | Обязательна | Описание |
|---|---|---|
| `VPS_DOMAIN` | да | Публичный домен VPS, например `94-228-117-27.sslip.io` |
| `PMA_MYSQL_PASSWORD` | да | Пароль MySQL-пользователя `pma_admin` (отдельно от `MYSQL_ROOT_PASSWORD`) |
| `MYSQL_ROOT_PASSWORD` | да (для `apply`) | Пароль root MySQL на хосте — нужен скрипту `vps-phpmyadmin-mysql.sh` |
| `PMA_SUBDOMAIN` | нет | Префикс субдомена (по умолчанию `pma` → `pma.${VPS_DOMAIN}`) |
| `PHPMYADMIN_PORT` | нет | Порт upstream на localhost (по умолчанию `8085`) |
| `CERTBOT_EMAIL` | для первого cert | Email Let's Encrypt — для `vps-phpmyadmin.sh issue-cert` |

Дополнительно (export или при вызове скриптов):

| Переменная | По умолчанию | Описание |
|---|---|---|
| `COMPOSE_FILE` | `docker-compose.yml:docker-compose.prod.yml` | Overlay compose для production |
| `PMA_DOMAIN` | `${PMA_SUBDOMAIN}.${VPS_DOMAIN}` | Полный субдомен phpMyAdmin |
| `HTPASSWD_FILE` | `/etc/nginx/.htpasswd-phpmyadmin` | Файл HTTP Basic Auth nginx |
| `PMA_MYSQL_USER` | `pma_admin` | Имя MySQL-пользователя phpMyAdmin |

Перед первым запуском на VPS создайте `phpmyadmin/config.user.inc.php` из шаблона и сгенерируйте `blowfish_secret` (32 символа):

```bash
cp phpmyadmin/config.user.inc.php.example phpmyadmin/config.user.inc.php
openssl rand -base64 24   # подставить в blowfish_secret
```

Файл `phpmyadmin/config.user.inc.php` **не коммитить** (содержит секрет).

#### Порядок развёртывания на VPS

Выполнять **на VPS** из корня репозитория. Предварительно должен быть настроен основной HTTPS (`scripts/vps-nginx-ssl.sh all`) и healthcheck `mysql-host` в compose — `healthy`.

```bash
cd ~/apps/exampleProjectSail   # или ваш DEPLOY_PATH

# 1. Переменные в корневом .env:
#    VPS_DOMAIN, PMA_MYSQL_PASSWORD, MYSQL_ROOT_PASSWORD, PHPMYADMIN_PORT (опционально)

# 2. Hardening-конфиг phpMyAdmin (blowfish_secret — см. выше)
cp phpmyadmin/config.user.inc.php.example phpmyadmin/config.user.inc.php
# отредактировать blowfish_secret

export COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml

# 3. MySQL-пользователь pma_admin (один раз или при смене пароля)
./scripts/vps-phpmyadmin-mysql.sh apply
./scripts/vps-phpmyadmin-mysql.sh check

# 4. Контейнер phpMyAdmin (только localhost:8085)
docker compose up -d phpmyadmin

# 5. Host nginx: Basic Auth + TLS для субдомена pma.*
export VPS_DOMAIN=94-228-117-27.sslip.io
export CERTBOT_EMAIL=your@email.com
./scripts/vps-phpmyadmin.sh create-htpasswd   # интерактивно, один раз
./scripts/vps-phpmyadmin.sh all
./scripts/vps-phpmyadmin.sh check
```

**Вход в браузере:** `https://pma.${VPS_DOMAIN}` → HTTP Basic Auth (nginx) → логин `pma_admin` / `PMA_MYSQL_PASSWORD`.

CD через `.github/workflows/deploy.yml` поднимает контейнер `phpmyadmin` при `docker compose up -d`, но **не** настраивает nginx, certbot и `pma_admin` — это одноразовые шаги на VPS вручную.

#### Скрипты и файлы

| Файл | Назначение |
|---|---|
| `docker-compose.prod.yml` | Сервис `phpmyadmin`, порт `127.0.0.1:${PHPMYADMIN_PORT:-8085}` |
| `phpmyadmin/config.user.inc.php.example` | Шаблон hardening без секретов |
| `scripts/vps-phpmyadmin-mysql.sh` | Создание/проверка `pma_admin` (`apply`, `check`) |
| `scripts/mysql-create-pma-admin.sql` | SQL-шаблон (пароль подставляет обёртка) |
| `scripts/vps-phpmyadmin.sh` | nginx site, certbot, htpasswd (`all`, `check`, …) |

Команды `vps-phpmyadmin.sh`: `install-deps`, `create-htpasswd`, `issue-cert`, `apply-nginx`, `all`, `check` — подробности в `./scripts/vps-phpmyadmin.sh` без аргументов.

#### Безопасность (рекомендации)

- **UFW:** не открывать порты `8085` и `3306` наружу.
- **Не задавать** `PMA_USER` / `PMA_PASSWORD` в compose — иначе phpMyAdmin выполнит авто-логин без проверки.
- **Не коммитить:** `.htpasswd`, реальные пароли, `blowfish_secret`, `phpmyadmin/config.user.inc.php`.
- **Ротация:** периодически менять `PMA_MYSQL_PASSWORD` и пароли htpasswd; после смены MySQL-пароля — `./scripts/vps-phpmyadmin-mysql.sh apply`.
- **fail2ban** (уже ставится `scripts/vps-bootstrap.sh`): при желании добавить jail для nginx `auth_basic` — отдельный необязательный шаг.
- Опционально в nginx site: `allow <ваш_IP>; deny all;` перед `proxy_pass`.

### Отладка MAX mini-app (гибрид)

Для разработки mini-app на **локальной машине** с публичным HTTPS используйте SSH reverse tunnel на VPS (отдельный dev-домен, не prod):

```bash
cp scripts/vps-tunnel.env.example scripts/vps-tunnel.env
./scripts/setup-max-vps.sh
./scripts/vps-tunnel-watch.sh   # отдельный терминал
```

Подробности: [service-c/README.md](service-c/README.md) → «VPS hybrid».

### Обязательные GitHub Secrets

| Secret | Назначение |
|---|---|
| `DEPLOY_HOST` | Хост VPS |
| `DEPLOY_USER` | SSH-пользователь |
| `DEPLOY_PATH` | Путь к проекту на сервере |
| `DEPLOY_SSH_KEY` | Приватный SSH-ключ |

Опционально: `DEPLOY_PORT` (по умолчанию `22`).

Рекомендуется защитить окружения `production` и `production-migrations` правилами approval в GitHub.

## Единый тестовый контур

Скрипт `scripts/test-services.sh` работает через Docker Compose, пересоздаёт тестовые БД (`sail_db_testing` для `main-app` / `service-a` / `service-b` / `service-c` / `service-e`, `service_d_db_testing` для `service-d`), затем применяет миграции в порядке `main-app` → `service-a` → `service-b` → `service-c` (для `service-e` отдельные миграции не нужны — общая таблица `sales_outlets`). В режиме `all` подготовка выполняется перед тестами каждого сервиса, потому что `RefreshDatabase` внутри тестов может менять схему общей тестовой БД.

Подготовить только тестовую БД:

```bash
./scripts/test-services.sh prepare
```

Подготовить БД и запустить все тесты:

```bash
./scripts/test-services.sh all
```

Подготовить БД и запустить тесты одного сервиса:

```bash
./scripts/test-services.sh main-app
./scripts/test-services.sh service-a
./scripts/test-services.sh service-b
./scripts/test-services.sh service-c
./scripts/test-services.sh service-d
./scripts/test-services.sh service-e
```

Быстрый повторный запуск без пересоздания БД:

```bash
./scripts/test-services.sh service-a --no-prepare
```

Пароль задаётся в `.env.testing.local` (не коммитится в git):

```bash
TEST_DB_PASSWORD=<your-local-password>
```

Переопределение через переменные окружения:

```bash
TEST_DATABASE=sail_db_testing \
TEST_DB_HOST=host.docker.internal \
TEST_DB_PORT=3306 \
TEST_DB_USERNAME=root \
TEST_DB_PASSWORD=<your-local-password> \
./scripts/test-services.sh all
```

Для тестов используйте только базу `sail_db_testing` и убедитесь, что тестовое окружение не указывает на рабочую БД.

Миграции изменяют состояние базы данных — запускайте `prepare` и режимы без `--no-prepare` только после явного согласия на выполнение миграций.

## База данных

Внутренний MySQL-контейнер в `docker-compose.yml` закомментирован: локально проект использует внешний MySQL на хосте.

Для доступа с контейнеров используется `host.docker.internal` (пробрасывается через `extra_hosts: host-gateway` у `main-app`, `service-a`, `service-b`, `service-b-queue`).

В CI overlay `docker-compose.ci.yml` поднимает контейнер `mysql:8.0` внутри compose-сети.

## Примечания

- `main-app` — Nginx + PHP-FPM 8.4 Alpine + Supervisor, внутренний порт `8000`; для hot-reload смонтированы `app`, `routes`, `config`, `database`, `resources`, `tests`, `public`, `storage`.
- `service-a` — `php artisan serve` на порту `8000`, полный bind-mount каталога сервиса и `shared`.
- `service-b` — selective bind-mount (без перезаписи `vendor`); `service-b-queue` использует тот же образ; live-stats идут через domain event `SalesOutletReportJobMutated` и listeners; broadcast и очередь зависят от `reverb`, `redis`, `mailhog`.
- `service-c` — MAX mini-app, webhook, Vite entry `max-app`, сборка в `public/max-build/`; см. [service-c/README.md](service-c/README.md).
- `service-d` — Vue 3 SPA + Sanctum на субдомене `yandexmaps.*`; resolve/confirm организации, отзывы, очередь `service-d-queue`; host-based routing без `auth_request`; БД `service_d_db`; см. [service-d/README.md](service-d/README.md).
- `reverb` — отдельный контейнер на базе образа `main-app`, порт `8090`; для браузера в `.env` указывайте `VITE_REVERB_HOST=localhost`, внутри сети Docker — `REVERB_HOST=reverb`.
- `nginx-gateway/auth.lua` не используется: `access_by_lua_file` в `nginx.conf` закомментирован.
- `PASSPORT_CLIENT_SECRET` в gateway нужен только при схеме OAuth client credentials на стороне gateway; для текущего `auth_request` secret не обязателен при первом запуске.
- Redis используется сервисами и Reverb; MailHog — для перехвата почты в dev (`html_email`); MAX — исходящий HTTPS из `service-b` к `platform-api.max.ru` (`max_message`).
