# CI Definition of Done (DoD)

Этот документ фиксирует утверждённые критерии готовности CI перед дальнейшими изменениями workflow.

## Обязательные критерии

- Workflow CI стабильно проходит на чистом прогоне (`push` и `pull_request`).
- Все backend-тесты запускаются внутри Docker-контекста проекта через `docker compose`.
- Frontend build запускается для `main-app` и `service-d`.
- Механизм кэша (Composer/npm) не влияет на воспроизводимость результата: при cache miss и cache hit итог одинаковый.
- Миграции и подготовка БД выполняются только в тестовом контуре.

## Критерии тестового контура БД

- Для тестов `main-app`, `service-a`, `service-b`, `service-c` используется только база `sail_db_testing`.
- Для `service-d` — отдельная база `service_d_db_testing` (см. `scripts/test-services.sh`, `service-d/.env.testing`).
- Параметры `TEST_*` должны быть явно заданы в CI-окружении.
- Backend-тесты не должны обращаться к production/staging БД.

## Минимальный обязательный набор CI job

- `php-style` — Laravel Pint для `main-app`, `service-a`, `service-b`, `service-c`, `service-d`.
- `frontend-build` — `npm ci` + `npm run build` для `main-app` и `service-d`.
- `backend-tests` — Docker Compose с overlay `docker-compose.ci.yml`, затем `./scripts/test-services.sh all` (все пять PHP-сервисов).

## Проверка перед реализацией следующих этапов

Считать этот этап закрытым, если одновременно выполнены пункты ниже:

- В `.github/workflows/ci.yml` присутствуют триггеры `push` и `pull_request`.
- В CI-пайплайне есть шаги: checkout, cache deps, `docker compose build`, `docker compose up -d`, ожидание готовности MySQL, запуск `./scripts/test-services.sh all`, frontend build.
- Скрипт `scripts/test-services.sh` использует `sail_db_testing` и `service_d_db_testing` как дефолтные тестовые БД.
- Локально CI-контур backend-тестов воспроизводится командами из [scripts.md](scripts.md) (раздел «Тесты и CI») или [README.md](../README.md) → «CI».

Опционально (вне обязательного CI): `./scripts/e2e-verify-report-stats.sh` — ручная E2E-проверка WebSocket-статистики отчётов.

## Каталог скриптов

Актуальный список файлов в `scripts/`: [scripts.md](scripts.md).

## Статус утверждения

Критерии зафиксированы и приняты как базовый DoD для CI. Реализация последующих задач CI/CD должна соответствовать этому документу.
