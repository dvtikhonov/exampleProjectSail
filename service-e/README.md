# service-e

Symfony 8 API-сервис торговых точек (`sales_outlets`). Использует общую таблицу MySQL с `service-a`; отдельные миграции не добавляются.

## Стек

- PHP 8.4, Symfony 8.1
- Doctrine ORM
- Общий пакет `shared/sales-outlets-domain` (Enums, DTO, Metadata)

## API

Базовый путь внутри контейнера: `/api/sales-outlets`. Через gateway: `/api/e/sales-outlets`.

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/api/sales-outlets` | Список с фильтрами, сортировкой и пагинацией |
| `PATCH` | `/api/sales-outlets/{id}` | Обновление торговой точки |
| `POST` | `/api/sales-outlets/{id}/head-organization` | Обновление головной организации |
| `DELETE` | `/api/sales-outlets/{id}` | Soft delete (`204`) |

Формат ответов совместим с `service-a`. Авторизация — заголовок `X-User-Id` от gateway (как в `service-a`).

## Локальный запуск

Сервис описан в корневом `docker-compose.yml`, порт по умолчанию **8086**:

```bash
docker compose up -d service-e
curl -H "X-User-Id: 1" http://localhost:8080/api/e/sales-outlets
```

Переменные окружения — см. `.env.example` (`DATABASE_URL`, `SALES_OUTLETS_STATUS_OPTIONS_ALL_LABEL`).

## Тесты

Используется общая тестовая БД `sail_db_testing` (таблица создаётся миграциями `service-a`).

```bash
./scripts/test-services.sh service-e
# или вместе со всеми сервисами:
./scripts/test-services.sh all
```

Внутри контейнера:

```bash
docker compose exec service-e php vendor/bin/phpunit --configuration phpunit.xml.dist
```

PHPUnit + `dama/doctrine-test-bundle` изолируют тесты транзакциями; фикстуры торговых точек — `tests/Support/SalesOutletTestSeeder.php`.

## Стиль кода

```bash
docker compose exec service-e vendor/bin/php-cs-fixer fix --dry-run --diff
```

Правила: `@Symfony` (см. `.php-cs-fixer.dist.php`).

## UI main-app

Страница «Объекты продаж 3»: `GET /objects-sales-outlets-3` — Inertia `ThirdIndex`, backend через `/api/e/`.
