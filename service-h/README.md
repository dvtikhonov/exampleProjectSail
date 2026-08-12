# service-h — стенд нагрузки (k6 + Grafana + InfluxDB)

Отдельный load-стенд для прогона сценариев против **service-c**. Не Laravel-приложение и **не** входит в корневой `docker-compose.yml` / CI / nginx-gateway.

Основа: [k6-load-test-starter-kit](https://github.com/VasilKham/k6-load-test-starter-kit). Цель основного сценария — полный клиентский поток с `orders/submit` **без** реальных сообщений в MAX.

| Сервис   | Host-порт      | Контейнер            |
|----------|----------------|----------------------|
| InfluxDB | **8089**→8086  | `service-h-influxdb` |
| Grafana  | **3002**→3000  | `service-h-grafana`  |

Порты отличаются от starter-kit (`8087`), чтобы не пересекаться с `service-f` / `service-g` в монорепо.

---

## Prerequisites

- Docker & Docker Compose
- Node.js (для `npm run`)
- **k6 на хосте WSL** (не в контейнере service-c)
- **Deep vs FPM:** локально у service-c в корневом `docker-compose.yml` задано `PHP_FPM_MAX_CHILDREN=200` (и spare/start 25/12/50), плюс `listen.backlog=1024` / `pm.max_requests=500`. Без этого при deep (пик ≈115 VU) очередь PHP-FPM и редкие request timeout (~60s). Prod оставляет default **64**. Подробнее — [service-c README → PHP-FPM pool](../service-c/README.md#php-fpm-pool-env).

### Ресурсы стенда (перед deep)

- **Docker Desktop / WSL2:** ориентир **≥4 CPU, ≥6–8 GB RAM** под deep (~115 VU + MySQL + Redis + Influx/Grafana).
- **Перед прогоном:** проверить пул FPM —
  `docker compose exec -T service-c grep max_children /usr/local/etc/php-fpm.d/zzz-www.conf`
  **или** `npm run preflight:deep` (из `service-h`; exit 0 = готово).
- **Во время soak:** `docker stats` — CPU `service-c` / `mysql` не должны долго сидеть в потолке 100%; нет OOM kill.
- **Xdebug:** для deep не `start_with_request=yes` (иначе latency и timeouts).
- **После смены `PHP_FPM_*` / шаблона FPM:** `docker compose build service-c && docker compose up -d --force-recreate service-c`, затем снова preflight / grep.
- **Регресс wall vs `t_submit`:** на пике VU снять `docker stats` и (временно) FPM status — `active` / `idle` / `listen queue`; см. [service-c README → PHP-FPM status](../service-c/README.md#php-fpm-status-при-регрессе-latency). Сводка Influx: `bash scripts/query_submit_compare.sh`. Summary k6 класть в `service-h/results/deep-*.json` (`k6 … --summary-export=…`).

### 0. Установка k6 в WSL (один раз)

Официальный apt-репозиторий Grafana (Ubuntu/Debian):

```bash
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
  --keyserver hkp://keyserver.ubuntu.com:80 \
  --recv-keys C5AD17C747E83913004B06FC9DCD74CFE1D30888
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
  | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install -y k6
k6 version
```

Проверка: `k6 version` должен вывести номер релиза.

**Fallback**, если apt недоступен:

- бинарник с [GitHub Releases k6](https://github.com/grafana/k6/releases) в `~/bin`, **или**
- одноразово: `docker run --rm -i grafana/k6 run -`

Основной путь для этого монорепо — native apt в WSL, чтобы `npm run test:*` работали как в starter-kit.

---

## Порядок прогона

Команды ниже — из **корня монорепо**, если не указано иное. k6 и `npm` — на **хосте WSL** (не в контейнере service-c).

`npm run test:food-order` (и `test:food-order:deep`) вызывает `scripts/run_food_order.sh`: при `PROFILE=deep` сначала **`preflight:deep`** (service-c, FPM 200/25/12/50 + backlog 1024 / max_requests 500, HTTP, ≥105 токенов, warn по `MAX_MESSENGER_DRIVER`), затем k6 deep (~21 мин) с выводом в InfluxDB. Голый `k6 run` preflight **не** делает.

### Полный прогон (первый раз / после сброса)

```bash
# 0. k6 и npm в WSL (один раз) — см. Prerequisites; при nvm: source ~/.nvm/nvm.sh
k6 version && node -v && npm -v

# 1. Цель: service-c + redis. MySQL в корневом compose нет —
#    БД на хосте (DB_HOST=host.docker.internal), должна уже быть доступна.
docker compose up -d service-c redis
# migrate / seed — только после явного согласия, если ещё не накатывали:
# docker compose exec -T service-c php artisan migrate
# docker compose exec -T service-c php artisan db:seed
# Seed даёт 3 ресторана / 15 блюд — достаточно для food_order_flow.
#
# После смены PHP_FPM_* / php-fpm.conf — rebuild + recreate и проверка пула:
# docker compose build service-c && docker compose up -d --force-recreate service-c
# docker compose exec -T service-c grep -E 'pm\.(max_children|start_servers|min_spare|max_spare|max_requests)|listen\.backlog' \
#   /usr/local/etc/php-fpm.d/zzz-www.conf
# ожидание: 200 / 25 / 12 / 50, backlog 1024, max_requests 500

# 2. Заглушка MAX (см. раздел ниже)
# в service-c/.env: MAX_MESSENGER_DRIVER=null
# по желанию опустошить MAX_UI_STAND_* (двойная страховка для admin-notifier)
docker compose exec -T service-c php artisan config:clear

# 3. Меню для k6 (обязательно, если cron food:sync-dish-availability обнулил is_available)
# Типичный симптом: «нет доступных блюд restaurant=N» в логе k6.
docker compose exec -T service-c php artisan max:load-test:prepare-menu

# 4. Токены (один VU = один max_user_id)
# Для PROFILE=deep желательно ≥105 токенов (100 VU stress + 5 изолированных competitive).
# Если service-h/tokens.json уже ≥105 — шаг можно пропустить.
docker compose exec -T service-c php artisan max:load-test:tokens 105
cp service-c/storage/app/load-test-tokens.json service-h/tokens.json

# 5. Мониторинг
docker compose -f service-h/docker-compose.yml up -d
# Grafana: http://localhost:3002

# 6. Нагрузка (WSL) — PROFILE=deep по умолчанию (~21 мин)
cd service-h
cp .env.example .env   # при необходимости (PROFILE=deep, TOKENS_FILE=../tokens.json)
BASE_URL=http://localhost:8083 TOKENS_FILE=../tokens.json npm run test:food-order
# то же с явным deep: ... npm run test:food-order:deep
# только проверка стенда: npm run preflight:deep
# короткий smoke (без preflight): npm run test:food-order:smoke
# без npm-обёртки (тоже без preflight):
#   BASE_URL=... TOKENS_FILE=../tokens.json PROFILE=deep \
#     k6 run --out influxdb=http://localhost:8089/k6 scripts/food_order_flow.js
```

Во время soak: `docker stats` — CPU `service-c` не должен долго сидеть в 100%; Grafana — http://localhost:3002.

### Мини-шпаргалка «уже всё поднимали»

Стенд уже был настроен (FPM deep, токены, Influx/Grafana, `MAX_MESSENGER_DRIVER=null`). Перед повторным deep:

```bash
cd ~/exampleProjectSail
docker compose up -d service-c redis
docker compose exec -T service-c php artisan config:clear
docker compose exec -T service-c php artisan max:load-test:prepare-menu
docker compose -f service-h/docker-compose.yml up -d
cd service-h
BASE_URL=http://localhost:8083 TOKENS_FILE=../tokens.json npm run test:food-order
```

Если токенов нет / мало (`preflight` падает на count &lt; 105) — снова шаг 4 из полного прогона.

### После прогона — восстановление service-c

`cleanup` **не** возвращает MAX «как было». После deep/smoke нужны два разных шага: данные нагрузки и драйвер мессенджера.

#### 1. Очистить заказы и корзины load-теста

Из корня монорепо (только `APP_ENV=local|testing`):

```bash
docker compose exec -T service-c php artisan max:load-test:cleanup
# если токенов было 105 (deep):
# docker compose exec -T service-c php artisan max:load-test:cleanup 105
```

Удаляет `max_food_orders` и `max_carts` для `max_user_id` вида `900001+` (тот же диапазон, что у `max:load-test:tokens`).  
**Не** удаляет: самих load-user, Sanctum-токены, `tokens.json`, меню, FPM-настройки, переменные `.env`.

#### 2. Вернуть полный доступ к MAX (Bot API)

Пока в `service-c/.env` стоит `MAX_MESSENGER_DRIVER=null`, исходящие сообщения в MAX **не** уходят (`NullMaxMessengerClient`). Это намеренно для нагрузки — после тестов верните рабочий режим вручную:

```env
# service-c/.env — один из вариантов:
MAX_MESSENGER_DRIVER=http
# или удалите строку MAX_MESSENGER_DRIVER (дефолт в config/max.php — http)
```

Если перед прогоном опустошали `MAX_UI_STAND_CHAT_IDS` / `MAX_UI_STAND_USER_IDS` (двойная страховка) — восстановите прежние значения.  
`MAX_BOT_ACCESS_TOKEN`, webhook / mini-app URL k6 обычно не меняет — проверьте, что они по-прежнему заданы.

После любой правки `.env`:

```bash
docker compose exec -T service-c php artisan config:clear
```

Проверка: оформить тестовый заказ (не load-user) — в MAX должно прийти уведомление.

#### 3. Остановить мониторинг service-h (по желанию)

```bash
docker compose -f service-h/docker-compose.yml down
```

На service-c не влияет.

#### Что обычно не трогают

| Что | Зачем оставить |
|-----|----------------|
| FPM `max_children=200` (локальный compose) | Удобно для следующих deep; prod default 64 — только если нужен «как prod» |
| `tokens.json` / load-user `900001+` | Следующий прогон без `max:load-test:tokens` |
| Меню после `prepare-menu` | Можно оставить; cron `food:sync-dish-availability` снова может обнулить `is_available` |
| migrate / seed | Не нужны, если БД цела |

Краткий чеклист «снова обычная разработка + MAX»:

```bash
docker compose exec -T service-c php artisan max:load-test:cleanup 105
# в service-c/.env: MAX_MESSENGER_DRIVER=http  (+ MAX_UI_STAND_*, если обнуляли)
docker compose exec -T service-c php artisan config:clear
# опционально: docker compose -f service-h/docker-compose.yml down
```

---

## Заглушка MAX

На каждый `POST /api/food/orders/submit` service-c вызывает нотификаторы → `MaxMessengerClientInterface`. Без заглушки пойдут HTTP-запросы на `https://platform-api.max.ru`.

Перед нагрузкой в **локальном** `service-c/.env`:

```env
MAX_MESSENGER_DRIVER=null
```

- `http` (по умолчанию) — реальный Bot API (`HttpMaxMessengerClient`)
- `null` — `NullMaxMessengerClient`: no-op `sendMessage` / `sendInlineKeyboardMessage`, без сети

> Laravel читает unquoted `null` в `.env` как PHP `null`; `service-c` нормализует это к драйверу `null` (см. `config/max.php`).

Двойная страховка для admin-notifier: оставить пустыми `MAX_UI_STAND_CHAT_IDS` / `MAX_UI_STAND_USER_IDS` (и при необходимости другие `MAX_UI_STAND_*`).

После правки `.env` — `config:clear` (см. также [После прогона — восстановление service-c](#после-прогона--восстановление-service-c)).
---

## Grafana и метрики

1. Поднять стек: `docker compose -f service-h/docker-compose.yml up -d`
2. Открыть **http://localhost:3002** — анонимный доступ с ролью Admin (пароль не нужен)
3. Dashboard **k6 Performance Dashboard** (`uid: k6-service-h`) и datasource InfluxDB (`http://influxdb:8086`, БД `k6`) провиженятся из `grafana/`
4. k6 пишет метрики через `--out influxdb=http://localhost:8089/k6` (см. npm-скрипты)
5. После правки JSON дашборда Grafana подхватывает файл за ~10 с (`updateIntervalSeconds`); при необходимости Hard refresh в UI (Ctrl+Shift+R)

### Панели submit: wall-clock vs Server-Timing

| Панель | Что смотреть |
|--------|----------------|
| **Submit latency: wall-clock vs Server-Timing** | На одном графике: `food_submit_duration` p95/p99 и `food_submit_t_tx` / `t_notify` / `t_submit` p95 |
| **Submit means** | Средние: gap `wall − t_submit` ≈ очередь HTTP/FPM + сеть |
| **Submit max (хвост)** | Если `wall max` → десятки секунд, а `t_submit max` остаётся ~1 s — timeout / очередь, не SQL |

Интерпретация:

- **wall ↑, t_* плоские** → узкое место runtime (FPM workers / CPU хоста), не домен submit;
- **t_tx ↑ вместе с wall** → смотреть MySQL / lock draft-корзины;
- **t_notify ↑** → драйвер MAX не `null` или медленный dispatch.

Контейнеры: `service-h-influxdb`, `service-h-grafana`. Остановка:

```bash
docker compose -f service-h/docker-compose.yml down
```

---

## npm-скрипты

| Скрипт                   | Описание                                              |
|--------------------------|-------------------------------------------------------|
| `test:food-order`        | Food API → service-c, профиль `PROFILE` (default deep)|
| `test:food-order:deep`   | Углублённая проверка: stress 50/100 + soak + negative |
| `test:food-order:smoke`  | Короткий прогон 5→15 VU                               |
| `test:food-order:baseline` | Контроль latency: ~15 VU, затем пик 100 VU (~6 мин); Server-Timing `t_tx`/`t_notify` |

Переменные окружения (см. `.env.example`):

| Переменная    | По умолчанию              | Назначение                          |
|---------------|---------------------------|-------------------------------------|
| `BASE_URL`    | `http://localhost:8083`   | Host-порт service-c                 |
| `TOKENS_FILE` | `../tokens.json`          | JSON токенов; путь от `scripts/` (k6 `open`) |
| `PROFILE`     | `deep`                    | `deep` \| `smoke` \| `baseline`     |
| `INFLUX_URL`  | `http://localhost:8089/k6`| Справка; в npm-скриптах URL зашит   |

### Профиль `deep` (по умолчанию)

Для deep желательно **≥105** токенов (100 VU stress + 5 изолированных для competitive).

Перед прогоном убедитесь, что локальный service-c поднят с **`PHP_FPM_MAX_CHILDREN=200`** (корневой `docker-compose.yml`); иначе при ~115 VU запросы встают в очередь FPM и возможны timeout. См. [Ресурсы стенда](#ресурсы-стенда-перед-deep), Prerequisites и [service-c FPM](../service-c/README.md#php-fpm-pool-env).

| Сценарий k6     | Назначение |
|-----------------|------------|
| `stress_soak`   | Ramp 15→50→100 VU, затем soak ~15 мин на 100 VU |
| `competitive`   | 10 VU, пул из **последних** 5 токенов (изолирован от stress); ресторан закреплён за user (`tokenIndex % R`); гонки корзины |
| `negative`      | 5 VU, невалидный `dish_id` / пустой адрес → ожидание 4xx |

Пороги: `submit` / `food_submit_duration` **только** `{scenario:stress_soak}` — p95 &lt; 1500 ms, p99 &lt; 3000 ms; failed rate stress_soak &lt; 1%; competitive — checks &gt; 95% (201 или ожидаемый 422 «Корзина пуста»), без жёсткого `http_req_failed`; negative — ожидаемые 4xx через `expectedStatuses` (не в `http_req_failed`).

В Grafana смотреть хвост latency (p99/max) и корреляцию пиков с ramp-up.
**Last verified (deep):** `20260811T123832Z` — summary `results/deep-20260811T123832Z.json`. stress_soak: food_submit p95≈627 ms, p99≈1.17 s, http_req_failed≈0.04%; all deep thresholds green. Influx (last 3h): mean wall−t_submit≈382 ms (plateau 5m means ≈420–480 ms; prior gap note 400–650 ms).


### Профиль `smoke`

Прежний короткий прогон: stages ~5→15 VU, `http_req_failed < 1%`, `submit` p95 &lt; 1500 ms.

### Профиль `baseline` (профилирование submit)

Короткий контроль очереди runtime (`artisan serve`):

1. `baseline_smoke` — ~15 VU (~1.5 мин);
2. `baseline_100` — ramp 50→100 VU, пик ~2 мин.

Сравнивать:

- k6 `http_req_duration{name:submit}` / `food_submit_duration` (wall-clock, включает очередь HTTP);
- Trends `food_submit_t_tx`, `food_submit_t_notify`, `food_submit_t_submit` из заголовка `Server-Timing` ответа `POST /orders/submit`.

Если при 100 VU wall-clock растёт до секунд, а `t_tx`+`t_notify` остаются десятки–сотни ms — доминирует однопоточный runtime, не SQL/notifiers.

---

## Структура

```
service-h/
  docker-compose.yml          # InfluxDB + Grafana
  grafana/                    # provisioning + dashboard
  scripts/
    food_order_flow.js        # основной сценарий
  package.json
  .env.example
  tokens.json                 # локально, в .gitignore
```

---

## Предупреждения

- **Не гонять против продакшена** — только локальный / тестовый service-c.
- **БД быстро растёт** заказами и корзинами load-пользователей (`max_user_id` вида `900001+`), особенно на `PROFILE=deep` (~100 VU × 15+ мин). После прогона — `max:load-test:cleanup` и при необходимости возврат `MAX_MESSENGER_DRIVER=http` (см. [После прогона](#после-прогона--восстановление-service-c)); `cleanup` сам MAX не включает.
- Миграции и seed в service-c — **только после явного согласия** в момент прогона.
- Стек мониторинга поднимается **отдельно** от корневого compose; в CI и nginx-gateway не подключается.
- В первом сценарии не тестируем webhook MAX и admin API.
