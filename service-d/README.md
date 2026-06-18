# service-d — Vue SPA + Sanctum: организации и отзывы Яндекс.Карт

Laravel 13 (PHP 8.4) и Vue 3 SPA с авторизацией через **Laravel Sanctum** (cookie + CSRF на том же origin). Пользователь привязывает **одну организацию** с Яндекс.Карт (поиск по URL, ссылке на сайт или тексту с уточнением города), подтверждает кандидата и просматривает **синхронизированные отзывы**. Сбор страницы — в Playwright-сервисе `yandex-parser`; разбор кандидатов и бизнес-логика — в `service-d`. Отдельная MySQL-база `service_d_db`.

| Документ | Назначение |
|---|---|
| [корневой README](../README.md) | Docker, gateway, CI/CD, общая инфраструктура |
| [yandex-parser/README.md](../yandex-parser/README.md) | Playwright-сервис: `/resolve`, `/sync-reviews`, контракт collect |

Порт по умолчанию: **8084** (`SERVICE_D_PORT` в `docker-compose.yml`). Vite dev: **5175** (`SERVICE_D_VITE_PORT`).

## Разделение ответственности: service-d ↔ yandex-parser

Два сервиса разделены по принципу **«браузер vs бизнес»**. `yandex-parser` — stateless-адаптер к Яндекс.Картам (Playwright, только Docker-сеть). `service-d` — единственная точка входа для клиента: авторизация, доменная логика, БД, очередь, SPA.

| Область | `service-d` (Laravel) | `yandex-parser` (Node + Playwright) |
|---|---|---|
| Публичный HTTP API | ✅ `/api/*`, Vue SPA | ❌ только внутренние `POST /resolve`, `POST /sync-reviews` |
| Авторизация / сессии | ✅ Sanctum, cookie, CSRF | ❌ |
| База данных | ✅ `organizations`, `organization_reviews`, `jobs` | ❌ |
| Очередь и статусы sync | ✅ `service-d-queue`, `sync_status`, `sync_error` | ❌ |
| Ввод пользователя | ✅ свободный текст, сайт + город → `resolverUrl` | ❌ принимает только готовый URL Яндекс.Карт |
| Сессия resolve (кандидаты) | ✅ `session_id` в cache, confirm, auto-select | ❌ |
| Фильтр по уточнению города | ✅ `filterCandidatesByClarification` | ❌ |
| Браузер / антибот | ❌ | ✅ Chromium, `humanMouseJiggle`, таймауты |
| Навигация и скролл страницы | ❌ | ✅ goto, скролл выдачи / отзывов |
| Перехват XHR/fetch JSON | ❌ | ✅ `NetworkJsonCollector` |
| Сырой collect (`/resolve`) | ❌ не открывает браузер | ✅ `network_payloads`, `dom_harvest`, `page_meta` |
| Сборка кандидатов организации | ✅ `OrganizationCandidateBuilder` и parsing-слой | ❌ поле `candidates` **не возвращается** |
| Парсинг org + отзывов (`/sync-reviews`) | ❌ не разбирает DOM | ✅ `sync/syncReviews.ts`, `orgExtract`, `reviewExtract` |
| Сохранение отзывов | ✅ `replaceForOrganization`, пагинация API | ❌ |
| Ошибки для UI | ✅ `502` (парсер), `422` (домен), `sync_error` | ✅ `400`/`422`/`500` с `{ error, message }` |

### Асимметрия по эндпоинтам

| Эндпоинт | `yandex-parser` отдаёт | `service-d` делает дальше |
|---|---|---|
| `POST /resolve` | **Сырьё страницы** — без бизнес-интерпретации | Строит `candidates[]`, считает `match_count`, кладёт сессию в cache |
| `POST /sync-reviews` | **Готовые** `org` + `reviews[]` | Обновляет метаданные организации, заменяет отзывы в БД, выставляет `sync_status` |

Правило: логику **выбора организации пользователем** и **жизненного цикла данных** не переносить в `yandex-parser`. Логику **работы с браузером и DOM/API Яндекса** не переносить в `service-d`.

### Потоки (сквозные)

```text
resolve
───────
Браузер → service-d POST /api/organization/resolve
        → service-d: валидация ввода, построение resolverUrl
        → yandex-parser POST /resolve (сырой collect)
        → service-d: OrganizationCandidateBuilder → session_id + candidates

sync
────
Браузер → service-d POST /api/organization/confirm
        → service-d-queue: SyncYandexOrganizationReviewsJob
        → yandex-parser POST /sync-reviews (org + reviews)
        → service-d: запись в БД, sync_status → completed | failed
```

### Контракты и лимиты

| Параметр | Где задаётся | Смысл |
|---|---|---|
| `RESOLVE_CANDIDATE_LIMIT` | `yandex-parser` | Глубина скролла на странице поиска при collect |
| `YANDEX_PARSER_RESOLVE_CANDIDATE_LIMIT` | `service-d` | Лимит кандидатов **после** merge/dedupe в PHP |
| `YANDEX_PARSER_URL` | `service-d` | Базовый URL парсера (`PlaywrightYandexMapsClient`) |

При изменении JSON-ответа `/resolve` или `/sync-reviews` обновляйте **оба** сервиса в одном деплое: DTO/мапперы в `service-d` (`ParserCollectResultDto`, `ParsedReviewDto`) и типы в `yandex-parser` (`types.ts`).

### Где писать код и тесты

| Задача | Сервис | Тесты |
|---|---|---|
| Новый способ извлечь карточку из выдачи | `service-d` → `Parsing/*` | `tests/Unit/YandexMaps/`, фикстуры `tests/Fixtures/yandex/collect/` |
| Скролл, перехват сети, DOM-harvest | `yandex-parser` → `resolve/` | `yandex-parser/tests/` (vitest) |
| Извлечение отзывов со страницы org | `yandex-parser` → `sync/`, `reviewExtract` | `yandex-parser/tests/reviewExtract.test.ts` |
| API confirm, resync, пагинация | `service-d` | `tests/Feature/OrganizationApiTest.php` + `FakesYandexParser` |

Подробности HTTP-контракта парсера: [`yandex-parser/README.md`](../yandex-parser/README.md#разделение-ответственности-service-d--yandex-parser).

## Маршрутизация

| Путь | Куда | Авторизация |
|---|---|---|
| `http://localhost:8084/` | Vue SPA (прямой доступ к контейнеру) | Sanctum (cookie) |
| `http://yandexmaps.localhost:8080/` | Через nginx-gateway (host-based routing) | Sanctum |
| `https://yandexmaps.94-228-117-27.sslip.io/` | Production (host nginx → gateway → service-d) | Sanctum |
| `http://localhost:8084/up` | Health check Laravel | Публичный |
| `POST /api/register`, `POST /api/login` | Регистрация и вход | Публичный |
| `POST /api/logout`, `GET /api/user` | Сессия пользователя | `auth:sanctum` |
| `GET/POST /api/organization/*` | Организация и отзывы | `auth:sanctum` |

**Важно:** service-d обслуживается **целиком на субдомене** `yandexmaps.*` — без префикса `/api/d/` и без `auth_request` gateway. Gateway маршрутизирует по заголовку `Host` (см. `nginx-gateway/nginx.conf`).

### Маршруты SPA (Vue Router)

| Путь | Компонент | Описание |
|---|---|---|
| `/login` | `Login.vue` | Вход / регистрация |
| `/` | `HomeRedirect.vue` | Редирект: `/reviews` если организация есть, иначе `/settings` |
| `/settings` | `Settings.vue` | Поиск и подтверждение организации, статус синхронизации, пересинхронизация |
| `/reviews` | `Reviews.vue` | Список отзывов с пагинацией, опрос `sync-status` пока идёт синхронизация |

## Домен организации (кратко)

- **Один пользователь — одна организация** (`organizations.user_id` unique). Повторное подтверждение заменяет предыдущую запись.
- **Resolve** — `POST /api/organization/resolve` с полем `url`: ссылка Яндекс.Карт, сайт или текст вида `invitro новокузнецк`. Ответ: `session_id`, `candidates[]`, `match_count`, `auto_selected`.
- **Confirm** — `POST /api/organization/confirm` с `session_id` + `org_id` из списка кандидатов. Ставит `SyncYandexOrganizationReviewsJob` в очередь.
- **Синхронизация отзывов** — фоновый воркер `service-d-queue` вызывает `yandex-parser` `/sync-reviews` и сохраняет записи в `organization_reviews`.
- **Статусы** (`OrganizationSyncStatus`): `pending` → `syncing` → `completed` | `failed` (поле `sync_error`).

## Структура (ключевые каталоги)

```
service-d/
├── app/
│   ├── Clients/                    # PlaywrightYandexMapsClient
│   ├── Contracts/                  # YandexMapsClient, Organization*, CandidateBuilder
│   ├── DTO/YandexMaps/             # Resolve, Confirm, ParsedReview, ParserCollectResult, …
│   ├── Enums/                      # OrganizationSyncStatus
│   ├── Exceptions/                 # Organization*, YandexMapsParserException
│   ├── Http/Controllers/Api/       # AuthController, OrganizationController
│   ├── Http/Requests/              # Auth, Organization (валидация входа)
│   ├── Jobs/                       # SyncYandexOrganizationReviewsJob
│   ├── Models/                     # User, Organization, OrganizationReview
│   ├── Repositories/Organization/  # EloquentOrganization*, review repository
│   └── Services/
│       ├── Auth/                   # AuthService
│       └── YandexMaps/             # Resolve, Confirm, Sync, Resync, ReviewQuery
│           └── Parsing/            # CandidateBuilder, DomHarvestMapper, JsonTreeWalker, …
├── database/migrations/            # users, sessions, organizations, organization_reviews, jobs
├── resources/js/spa-app/           # Vue 3 SPA
│   ├── api/client.js               # axios + withCredentials
│   ├── composables/                # useAuth, useOrganization, useOrganizationReviews
│   ├── components/                 # AuthErrorAlert, LoadingSpinner
│   └── pages/                      # Login, HomeRedirect, Settings, Reviews
├── routes/api.php, web.php
├── tests/
│   ├── Feature/                    # AuthApiTest, OrganizationApiTest
│   ├── Unit/YandexMaps/            # парсинг на фикстурах collect
│   ├── Fixtures/yandex/            # сырые ответы yandex-parser
│   └── Support/                    # FakesYandexParser, MakesStatefulApiRequests
├── docker-entrypoint.sh            # auto `npm run build` при отсутствии manifest
└── Dockerfile
```

Связанные Docker-сервисы: **`service-d`** (HTTP), **`service-d-queue`** (`php artisan queue:work`), **`yandex-parser`** (Playwright).

## Быстрый старт (локально)

```bash
cp service-d/.env.example service-d/.env
docker compose build service-d yandex-parser
docker compose up -d service-d service-d-queue yandex-parser gateway
```

Для полного сценария (resolve → confirm → sync отзывов) нужны все три: `service-d`, `service-d-queue`, `yandex-parser`.

Для доступа через gateway добавьте в `/etc/hosts` (Linux/WSL):

```text
127.0.0.1 yandexmaps.localhost
```

Откройте `http://yandexmaps.localhost:8080/` — форма входа; после login — `/settings` (если организация не настроена) или `/reviews`.

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

## Переменные окружения

Ключевые поля в `service-d/.env`:

```env
APP_URL=http://localhost:8084
DB_DATABASE=service_d_db
SESSION_DRIVER=database
QUEUE_CONNECTION=database
SANCTUM_STATEFUL_DOMAINS=localhost:8084,localhost,yandexmaps.localhost,yandexmaps.localhost:8080,__SANCTUM_CURRENT_REQUEST_HOST__
YANDEX_PARSER_URL=http://yandex-parser:3000
YANDEX_PARSER_RESOLVE_CANDIDATE_LIMIT=30
```

| Переменная | Описание |
|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | Домены для cookie-based API. Плейсхолдер `__SANCTUM_CURRENT_REQUEST_HOST__` подставляет `Host` запроса динамически |
| `YANDEX_PARSER_URL` | Базовый URL Playwright-сервиса (`config/services.php` → `yandex_parser.url`) |
| `YANDEX_PARSER_RESOLVE_CANDIDATE_LIMIT` | Лимит кандидатов после merge/dedupe (по умолчанию 30) |
| `QUEUE_CONNECTION` | Для синхронизации отзывов — `database`; обрабатывает `service-d-queue` |

Через gateway локально нужен порт в домене (`yandexmaps.localhost:8080`) — Origin браузера включает `:8080`.

**Production** (после `scripts/vps-nginx-ssl.sh`; основной домен VPS — `94-228-117-27.sslip.io`):

```env
APP_URL=https://yandexmaps.94-228-117-27.sslip.io
SANCTUM_STATEFUL_DOMAINS=yandexmaps.94-228-117-27.sslip.io
SESSION_DOMAIN=yandexmaps.94-228-117-27.sslip.io
```

`SESSION_DOMAIN` нужен, чтобы cookie сессии работала на субдомене.

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

Исходники SPA: `resources/js/spa-app/`. Точка входа — `app.js`, шаблон — `resources/views/spa.blade.php`, ассеты — `public/spa-build/`.

## API

### Авторизация (`auth:sanctum`)

| Метод | Путь | Тело | Ответ |
|---|---|---|---|
| `POST` | `/api/register` | `name`, `email`, `password`, `password_confirmation` | `201` + user |
| `POST` | `/api/login` | `email`, `password` | `200` + user |
| `POST` | `/api/logout` | — | `204` |
| `GET` | `/api/user` | — | `{ "user": … }` или `401` |

### Организация (`auth:sanctum`)

| Метод | Путь | Тело / query | Коды |
|---|---|---|---|
| `GET` | `/api/organization` | — | `200` (`organization: null` если не настроена) |
| `POST` | `/api/organization/resolve` | `{ "url": "…" }` | `200`, `422` (валидация), `502` (парсер) |
| `POST` | `/api/organization/confirm` | `{ "session_id": "uuid", "org_id": "123" }` | `202`, `422` (сессия/кандидат) |
| `GET` | `/api/organization/sync-status` | — | `200`, `404` |
| `POST` | `/api/organization/resync` | — | `202`, `404` |
| `GET` | `/api/organization/reviews` | `?page=1` | `200` + пагинация, `404` |

**Валидация `url` (resolve):** обязательная строка до 2048 символов. Допустимые форматы:

- ссылка Яндекс.Карт (`https://yandex.ru/maps/...`), опционально с уточнением через пробел;
- ссылка на сайт или домен + уточнение: `www.invitro.ru Новокузнецк`, `invitro новокузнецк`.

Сообщение об ошибке: *«Укажите ссылку в начале, затем при необходимости уточнение через пробел…»*.

Ответ `resolve` (фрагмент):

```json
{
  "session_id": "uuid",
  "match_count": 3,
  "auto_selected": false,
  "candidates": [
    {
      "org_id": "1038900970",
      "name": "Invitro",
      "address": "ул. Энтузиастов, 32, Новокузнецк",
      "average_rating": 4.68,
      "ratings_count": 682,
      "canonical_url": "https://yandex.ru/maps/org/invitro/1038900970/"
    }
  ]
}
```

Ответ `reviews` (фрагмент):

```json
{
  "organization": {
    "name": "Invitro",
    "average_rating": 4.68,
    "ratings_count": 682,
    "reviews_count": 24,
    "sync_status": "completed"
  },
  "reviews": {
    "data": [
      {
        "id": 1,
        "author_name": "Иван",
        "published_at": "2025-03-15T10:00:00+00:00",
        "text": "…",
        "rating": 5
      }
    ],
    "meta": { "current_page": 1, "last_page": 2, "per_page": 20, "total": 24 }
  }
}
```

## Интеграция с yandex-parser

См. раздел [**Разделение ответственности**](#разделение-ответственности-service-d--yandex-parser) выше. Ниже — краткие схемы вызовов и ссылка на контракт.

### Поток resolve

```text
POST /api/organization/resolve
  → OrganizationResolveService
  → PlaywrightYandexMapsClient::collect()  →  POST yandex-parser/resolve
  → OrganizationCandidateBuilder (PHP)     ←  network_payloads + dom_harvest
  → filterCandidatesByClarification
  → session в cache + JSON ответ
```

| Слой | Где | Классы |
|------|-----|--------|
| Сбор страницы | `yandex-parser` | Playwright, `NetworkJsonCollector`, `domHarvest` |
| Сборка кандидатов | `service-d` | `OrganizationCandidateBuilder`, `JsonTreeWalker`, `DomHarvestMapper`, `OrganizationRecordMapper`, `OrganizationCandidateMerger` |
| Оркестрация | `service-d` | `OrganizationResolveService`, `OrganizationConfirmService`, `PlaywrightYandexMapsClient` |

### Поток sync отзывов

```text
POST /api/organization/confirm  (или /resync)
  → SyncYandexOrganizationReviewsJob (очередь database)
  → service-d-queue: OrganizationSyncService
  → POST yandex-parser/sync-reviews
  → сохранение в organization_reviews, sync_status → completed | failed
```

**Правило деплоя:** после изменения формата ответа `/resolve` или `/sync-reviews` пересобирайте и перезапускайте `yandex-parser` **вместе с** `service-d` в одном релизе.

HTTP-контракт и переменные парсера: [`yandex-parser/README.md`](../yandex-parser/README.md).

## Синхронизация отзывов (очередь)

После подтверждения организации ставится `SyncYandexOrganizationReviewsJob` в очередь (`QUEUE_CONNECTION=database`). Обрабатывает контейнер **`service-d-queue`** (`php artisan queue:work --timeout=900`).

| `sync_status` | Значение |
|---|---|
| `pending` | Job в очереди, воркер ещё не взял задачу |
| `syncing` | Воркер запустил парсер `yandex-parser` |
| `completed` | Отзывы сохранены |
| `failed` | Ошибка (см. `sync_error`) |

Если статус долго остаётся **`pending`** — почти всегда не запущен или упал **`service-d-queue`**.

Диагностика:

```bash
./scripts/diag-service-d-sync.sh
```

На VPS:

```bash
export COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml
docker compose up -d service-d-queue yandex-parser
docker compose logs --tail=80 service-d-queue
```

После деплоя перезапустите воркер:

```bash
docker compose exec -T service-d-queue php artisan queue:restart
```

## Тесты

Используется только `service_d_db_testing` (см. `service-d/.env.testing` и `scripts/test-services.sh`):

```bash
./scripts/test-services.sh service-d
```

| Каталог | Содержание |
|---|---|
| `tests/Feature/AuthApiTest.php` | register, login, logout, user |
| `tests/Feature/OrganizationApiTest.php` | resolve, confirm, sync, reviews (с `FakesYandexParser`) |
| `tests/Unit/YandexMaps/` | парсинг на фикстурах `tests/Fixtures/yandex/collect/` |
| `tests/Unit/OrganizationSearchInputValidatorTest.php` | валидация ввода URL/текста |

Feature-тесты API с fake collect: `tests/Support/FakesYandexParser.php`, stateful Sanctum: `tests/Support/MakesStatefulApiRequests.php`.

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

## Ручная проверка на реальных URL

1. Пересобрать парсер после изменений в `yandex-parser/src/`:

```bash
docker compose build yandex-parser
docker compose up -d yandex-parser service-d service-d-queue
```

2. Проверить collect (из контейнера парсера):

```bash
docker compose exec -T yandex-parser node <<'NODE'
fetch('http://127.0.0.1:3000/resolve', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    url: 'https://yandex.ru/maps/?text=invitro%20новокузнецк',
  }),
}).then(async (r) => console.log(await r.json()));
NODE
```

Ожидается объект с полями `network_payloads`, `dom_harvest`, `page_meta` (не `candidates`).

3. Проверить сборку кандидатов в Laravel:

```bash
docker compose exec -T service-d php artisan tinker --execute="
\$svc = app(App\Services\YandexMaps\OrganizationResolveService::class);
\$r = \$svc->resolve(new App\DTO\YandexMaps\ResolveOrganizationDto(
    inputUrl: 'Новокузнецк invitro',
    resolverUrl: 'https://yandex.ru/maps/?text=invitro%20новокузнецк',
    searchText: 'invitro',
    clarification: 'Новокузнецк',
));
var_export(['match_count' => \$r->matchCount, 'auto_selected' => \$r->autoSelected]);
"
```

4. Через UI: войти на `http://yandexmaps.localhost:8080/` (или `:8084`), на `/settings` ввести URL/текст поиска, подтвердить кандидата, дождаться синхронизации на `/reviews`.

## Что не входит в текущий scope

- Яндекс.Карты SDK и отображение карты торговых точек (страница `Splash.vue` — legacy-заглушка, не подключена к роутеру)
- Прокси торговых точек из service-a / `shared/sales-outlets-domain`
- Общие пользователи с main-app
