# service-j

Java API + Vue 3 SPA для торговых точек (`sales_outlets`). Использует общую таблицу MySQL с `service-a` / `service-e`; отдельные миграции не добавляются. PHP-пакет `shared/sales-outlets-domain` в JVM не подключается — enums/DTO/metadata перенесены в модуль `sales-outlets-domain`.

**Связанные документы**

| Документ | Назначение |
|---|---|
| [корневой README](../README.md) | Docker Compose, gateway, Passport, общий тестовый контур |
| [service-a/README.md](../service-a/README.md) | Эталонный контракт API и миграции `sales_outlets` |
| [service-e/README.md](../service-e/README.md) | Symfony-реализация того же CRUD через `/api/e/` |

## Стек

| Компонент | Версия / примечание |
|---|---|
| Java | 21 |
| Spring Boot | 3.4 (Web, Data JPA, Bean Validation) |
| Сборка | Maven multi-module |
| БД (dev/prod) | та же MySQL, что у `service-a` (`sail_db`) |
| БД (тесты) | `sail_db_testing` |
| Frontend | Vue 3 + Vite + Vue Router + Tailwind CSS |

## Модули

```text
service-j/
├── sales-outlets-domain/          # Entity, enums, порты, domain DTO (без Spring/JPA)
├── sales-outlets-application/     # SalesOutletService (index/update/updateHO/delete)
├── sales-outlets-infrastructure/  # JPA, filter/sort/pagination, soft-delete, gateway user
├── sales-outlets-api/             # Controllers, @Valid, TrustGatewayAuthFilter, static SPA
├── frontend/                      # Vue 3 SPA (Vite); сборка → static api-модуля
├── Dockerfile                     # Node → Maven → eclipse-temurin:21-jre
└── pom.xml                        # parent multi-module
```

Runnable jar — только `sales-outlets-api`.

## API

Базовый путь внутри контейнера: `/api/sales-outlets`. Через gateway: `/api/j/sales-outlets`.

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/api/sales-outlets` | Список с фильтрами, сортировкой и пагинацией (5–50) |
| `PATCH` | `/api/sales-outlets/{id}` | Полное обновление (+ ValidRussianInn / HeadOrganizationType) |
| `POST` | `/api/sales-outlets/{id}/head-organization` | Обновление головной организации и типа |
| `DELETE` | `/api/sales-outlets/{id}` | Soft delete (`204`) |

Формат ответов совместим с `service-a` (`data` + `meta.columns|filters|pagination|status_options`).

Авторизация на уровне сервиса — заголовок `X-User-Id` от gateway (`TrustGatewayAuthFilter`). Отсутствует или невалидный → `401` `{"message":"Unauthorized gateway user."}`.

Валидация входа обязательна (Bean Validation + кастомные constraints для ИНН и типа ГО).

## CORS

Отдельная CORS-политика **не нужна и не внедряется**.

| Где | Решение |
|---|---|
| Spring Boot | CORS не настраивается |
| `nginx-gateway` `location /api/j/` | без `Access-Control-*`, без отдельного `OPTIONS`→204 (в отличие от `/api/a/` и `/api/e/`) |
| Браузер | SPA `http://localhost:8080/objects-sales-outlets-4/` и API `http://localhost:8080/api/j/...` — один origin (same-origin) |

Прямой доступ к `:8085` — только для curl/тестов, не для браузерного SPA.

## Vue 3 SPA

Каталог: `frontend/`. Base path: `/objects-sales-outlets-4/` (history mode).

Через gateway (рекомендуется):

```text
http://localhost:8080/objects-sales-outlets-4/
```

API-клиент ходит на относительный путь `/api/j` с `Authorization: Bearer <passport-token>`.

### Как получить токен

Inertiа-обёртки в `main-app` для v1 нет. Токен берётся так же, как у страниц объектов продаж:

1. Войдите в `main-app` (`http://localhost` или через gateway `http://localhost:8080/login`).
2. SPA запрашивает `GET /get-api-token` (сессия main-app на том же gateway) и кэширует Bearer в `localStorage`.
3. При отсутствии сессии можно вручную положить токен в `localStorage` (`api_token`).

Vite dev (`frontend/`):

```bash
cd service-j/frontend
npm ci
npm run dev
```

Dev-server (порт `5174`) проксирует `/api/j` и `/get-api-token` на gateway (`VITE_GATEWAY_ORIGIN`, по умолчанию `http://localhost:8080`), чтобы не вводить отдельный origin и CORS.

## Локальный запуск

Сервис описан в корневом `docker-compose.yml`, порт по умолчанию **8085** (`SERVICE_J_PORT`):

```bash
docker compose up -d --build service-j
curl -H "X-User-Id: 1" http://localhost:8080/api/j/sales-outlets
```

UI через gateway:

```text
http://localhost:8080/objects-sales-outlets-4/
```

Переменные окружения — см. `.env.example` и блок `environment` у `service-j` в compose (`SPRING_DATASOURCE_*`, `SERVICE_J_DB_*`).

Миграции не запускаются в этом сервисе: схема `sales_outlets` создаётся миграциями `service-a`.

## Тесты

Используется общая тестовая БД `sail_db_testing` (таблица из миграций `service-a`).

```bash
./scripts/test-services.sh service-j
# или вместе со всеми сервисами:
./scripts/test-services.sh all
```

Быстрый повтор без пересоздания БД:

```bash
./scripts/test-services.sh service-j --no-prepare
```

Скрипт гоняет `mvn test` в образе `maven:3.9.9-eclipse-temurin-21` с JDBC на `sail_db_testing`.

Покрытие (основные):

| Слой | Что проверяется |
|---|---|
| Unit (domain / application) | enums, filters, validation (ИНН, тип ГО), service |
| `@SpringBootTest` / MockMvc | 4 endpoint’а + `401` без `X-User-Id` |
| Frontend | сборка Vite в Docker/CI (smoke); e2e в v1 нет |

## Вне скоупа v1

- Отчёты CSV/email/MAX (`service-b`)
- Пункт меню / Inertia-страница в `main-app`
- Собственные Flyway-миграции `sales_outlets`
- CORS в Spring или в gateway для `/api/j/`
