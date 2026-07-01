# Скрипты репозитория (`scripts/`)

Каталог вспомогательных shell- и Node-скриптов. Все команды выполняются **из корня репозитория** в WSL/Linux shell (см. `.cursor/rules/Shell-Execution.mdc`).

Локальный конфиг туннеля MAX на VPS (`scripts/vps-tunnel.env`) создаётся из `scripts/vps-tunnel.env.example` и **не коммитится** (см. `.gitignore`).

## Тесты и CI

| Файл | Назначение |
|---|---|
| `test-services.sh` | Подготовка тестовых БД (`sail_db_testing`, `service_d_db_testing`, `service_f_db_testing`) и запуск PHPUnit в Docker. Режимы: `prepare`, `all`, `main-app`, `service-a`, `service-b`, `service-c`, `service-d`, `service-e`, `service-f`, флаг `--no-prepare`. Используется в `.github/workflows/ci.yml`. |
| `e2e-verify-report-stats.sh` | Обёртка E2E: создаёт Passport-токен, проверяет `/broadcasting/auth`, запускает `.cjs` внутри контейнера `main-app`. |
| `e2e-verify-report-stats.cjs` | E2E: два WebSocket-клиента + REST статистики отчётов (`ReportJobStatsChanged`). |

Локально воспроизвести CI-контур backend-тестов:

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

Подробнее о тестовой БД: [README.md](../README.md) → «Единый тестовый контур», критерии CI: [ci-definition-of-done.md](ci-definition-of-done.md).

## VPS: bootstrap, SSL, деплой

| Файл | Назначение |
|---|---|
| `vps-bootstrap.sh` | Первичная подготовка VPS: пакеты, Docker, UFW, fail2ban, подсказки по GitHub Secrets. |
| `vps-nginx-ssl.sh` | Let's Encrypt и nginx site для основного домена и субдомена `yandexmaps.*` (service-d). |
| `vps-git-sync.sh` | Ручная синхронизация рабочей копии на VPS с `origin/main` (`--apply` — сброс и `compose up`). Не используется в `deploy.yml`. |

Деплой в production — workflow `.github/workflows/deploy.yml` (SSH + `docker-compose.prod.yml`).

## VPS: phpMyAdmin

| Файл | Назначение |
|---|---|
| `vps-phpmyadmin-mysql.sh` | Создание/проверка MySQL-пользователя `pma_admin` (`apply`, `check`, `ensure-root-auth`). |
| `mysql-create-pma-admin.sql` | SQL-шаблон; пароль подставляет `vps-phpmyadmin-mysql.sh`. |
| `vps-phpmyadmin.sh` | nginx site, certbot, htpasswd для субдомена phpMyAdmin (`all`, `check`, …). |

Подробности: [README.md](../README.md) → «phpMyAdmin на VPS».

## MAX mini-app: VPS hybrid (SSH reverse tunnel)

| Файл | Назначение |
|---|---|
| `vps-tunnel.env.example` | Шаблон: `VPS_HOST`, `VPS_USER`, `VPS_DOMAIN`, порты. |
| `setup-max-vps.sh` | Полный цикл подготовки hybrid-режима (сборка + подсказки по nginx/туннелю). |
| `vps-tunnel.sh` | SSH reverse tunnel, nginx на VPS (`apply-nginx-remote`, `run`, `check`, `verify`, …). |
| `vps-tunnel-watch.sh` | Держит туннель с автоперезапуском (отдельный терминал). |
| `build-max-app.sh` | Production-сборка Vue в `service-c` (`public/max-build/`). |
| `diag-max-vps.sh` | Диагностика hybrid-контура (сборка, env, туннель). |
| `max-tunnel-check.sh` | Проверка доступности публичного URL mini-app. |

Подробности: [service-c/README.md](../service-c/README.md) → «VPS hybrid».

## MAX mini-app: fxTunnel (локальная разработка)

| Файл | Назначение |
|---|---|
| `setup-max-fxtun.sh` | Полный цикл: сборка, `.env`, webhook, подсказки по туннелю. |
| `start-max-tunnel.sh` | Быстрый старт: сборка + install fxTunnel + watch. |
| `fxtun-tunnel.sh` | Базовый клиент fxTunnel (`install`, `service-c run`, `check`, …). |
| `fxtun-exampleprojectsail.sh` | Туннель на `exampleprojectsail.fxtun.dev` (обёртка над `fxtun-tunnel.sh`). |
| `fxtun-exampleprojectsail-watch.sh` | Watch с автоперезапуском для `exampleprojectsail.fxtun.dev`. |
| `diag-max-fxtun.sh` | Диагностика fxTunnel-контура. |

## MAX mini-app: cloudflared (альтернатива)

| Файл | Назначение |
|---|---|
| `cloudflared-tunnel.sh` | Cloudflare quick tunnel (`install`, `service-c`, Docker-режим). Рекомендуется с VPN из РФ. |

## service-d и yandex-parser

| Файл | Назначение |
|---|---|
| `diag-service-d-sync.sh` | Диагностика синхронизации организаций service-d. |
| `debug-dump-yandex-org.sh` | Ручной вызов sync с дампом DOM/сети в `yandex-parser`. |

Подробности: [service-d/README.md](../service-d/README.md), [yandex-parser/README.md](../yandex-parser/README.md).
