# service-c — MAX mini-app «Заказ еды»

Backend (Laravel 13, PHP 8.4) и Vue 3 SPA для MAX mini-app: сеть ресторанов → меню → корзина → заявка (`draft` / `submitted`, без оплаты). Также webhook MAX, UI Stand (приветствие + inline-кнопки) и Artisan-команды `max:bot:info`, `max:webhook:*`, `max:ui-stand:send`.

| Документ | Назначение |
|---|---|
| [корневой README](../README.md) | Docker, gateway, общая инфраструктура |
| [shared/max-messenger](../shared/max-messenger/) | Общий HTTP-клиент MAX Bot API |

Порт по умолчанию: **8083** (`SERVICE_C_PORT` в `docker-compose.yml`). Vite dev: **5174** (`SERVICE_C_VITE_PORT`).

## Маршрутизация

| Путь | Куда | Авторизация |
|---|---|---|
| `http://localhost:8083/` | Welcome-страница Laravel | Публичный |
| `http://localhost:8083/max-app` | Vue SPA (прямой доступ) | Публичный |
| `http://localhost:8083/up` | Health check | Публичный |
| `http://localhost:8083/api/webhooks/max` | Webhook MAX | `X-Max-Bot-Api-Secret` |
| `http://localhost:8083/api/max/auth` | Валидация `initData` → Bearer token | Публичный |
| `http://localhost:8083/api/food/*` | Food API | Bearer (`max.miniapp.auth`) |
| `http://localhost:8080/api/c/...` | Через nginx-gateway | Gateway auth (`X-User-Id`) |
| `http://localhost:8080/api/c/webhooks/max` | Webhook через gateway | **Без** gateway auth |

**Важно:** MAX на **том же домене**, что и `main-app` (VPS sslip.io), идёт через `nginx-gateway` → `service-c` (`/max-app`, `/max-build/`, `/api/webhooks/max`, `/api/max/`, `/api/food/`). Ассеты mini-app в каталоге **`/max-build/`** (не `/build/`), чтобы не пересекаться с Vite `main-app`.

**Важно (отдельный туннель):** MAX (webhook и mini-app в dev) может обращаться по **публичному HTTPS URL** на порт **8083** через туннель, а не через gateway `:8080`. Gateway location `/api/c/webhooks/max` без `auth_request` нужен для локальной отладки и единообразия префиксов.

## Структура (ключевые каталоги)

```
service-c/
├── app/
│   ├── Contracts/                  # Auth, Max (DI-контракты)
│   ├── DTO/                        # Food, Max, Auth
│   ├── Enums/Food/                 # CartStatus, OrderStatus
│   ├── Http/Controllers/Api/       # Food, MaxAuth, MaxWebhook
│   ├── Http/Middleware/            # AuthenticateMaxMiniApp, TrustGatewayAuth, VerifyMaxWebhookSecret
│   ├── Http/Requests/              # Валидация входа (Food, Max)
│   ├── Models/                     # Restaurant, Dish, Cart, MaxUser, FoodOrder, …
│   ├── Services/
│   │   ├── Auth/                   # Gateway auth (Eloquent user resolver)
│   │   ├── Food/                   # CartService, MenuQueryService, OrderSubmissionService
│   │   └── Max/                    # initData validator, mini-app auth, UiStand
│   ├── Support/                    # MaxAppRequestContext, MaxOpenAppTargetResolver, MaxMiniAppAccessLogger
│   └── Console/Commands/           # max:bot:info, max:webhook:*, max:ui-stand:send, max:miniapp:verify
├── config/max.php                  # webhook, miniapp, ui_stand, retry
├── database/
│   ├── migrations/                 # max_users, food domain (префикс max_*)
│   └── seeders/RestaurantSeeder.php
├── resources/
│   ├── js/max-app/                 # Vue 3 SPA + MAX Bridge
│   │   ├── api/foodClient.js
│   │   ├── bridge/maxBridge.js
│   │   └── pages/                  # RestaurantList, MenuPage, CartPage, OrderConfirmationPage
│   ├── css/max-app.css             # Tailwind CSS
│   └── views/max-app.blade.php
├── routes/api.php, web.php
├── docker-entrypoint.sh            # auto `npm run build` при отсутствии manifest
└── tests/                          # Feature + Unit (БД: sail_db_testing)
```

Shared-пакет: `../shared/max-messenger` (`example/max-messenger` в `composer.json`) — HTTP-клиент MAX Bot API, DTO сообщений, retry-конфиг.

## Быстрый старт

```bash
cp service-c/.env.example service-c/.env
# Заполните MAX_BOT_ACCESS_TOKEN, MAX_WEBHOOK_SECRET (≥5 символов)
docker compose build service-c
docker compose up -d service-c
```

При первом запуске контейнер сам соберёт фронтенд, если нет `public/max-build/manifest.json` (см. `docker-entrypoint.sh`).

### База данных

Таблицы service-c с префиксом `max_*`: `max_users`, `max_restaurants`, `max_menu_categories`, `max_dishes`, `max_carts`, `max_cart_items`, `max_food_orders`. Общая MySQL с остальными сервисами (`sail_db`).

```bash
docker compose exec -T service-c php artisan migrate
docker compose exec -T service-c php artisan db:seed   # демо-рестораны «Пицца Макс», «Суши Бар»
```

> Миграции затрагивают только схему service-c. Перед выполнением в shared-окружении согласуйте с командой (в проекте три сервиса с отдельными migration paths).

### Фронтенд (Vite, порт 5174)

```bash
docker compose exec service-c npm run build   # для мобильных и туннеля (обязательно)
docker compose exec service-c npm run dev     # только локальная разработка на ПК
```

> **Мобильные (iOS/Android):** mini-app открывается через HTTPS-туннель. Используйте **`npm run build`**, не `npm run dev`.
> Dev-сервер Vite отдаёт ассеты с `localhost:5174` — с телефона этот адрес недоступен (белый экран).
> Маршрут `/max-app` при запросе не с `localhost`/`127.0.0.1` принудительно отключает Vite hot-file (`web.php` + `MaxAppRequestContext`).
> Если ранее запускали `npm run dev`, удалите `public/hot` в контейнере или пересоберите: `npm run build`.

### Тесты

```bash
./scripts/test-services.sh service-c
```

Скрипт пересоздаёт `sail_db_testing`, прогоняет миграции всех сервисов и запускает PHPUnit.

## Переменные окружения

### MAX

| Переменная | Назначение |
|---|---|
| `MAX_BOT_ACCESS_TOKEN` | Токен бота MAX (Bot API + валидация `initData`) |
| `MAX_BOT_USERNAME` | Username бота (`max:bot:info`) |
| `MAX_BOT_USER_ID` | `user_id` бота из `max:bot:info` — `contact_id` для кнопки `open_app` |
| `MAX_WEBHOOK_URL` | Публичный HTTPS URL webhook, напр. `https://exampleprojectsail.fxtun.dev/api/webhooks/max` |
| `MAX_WEBHOOK_SECRET` | Секрет webhook (минимум 5 символов), заголовок `X-Max-Bot-Api-Secret` |
| `APP_URL` | Корень сайта **без** `/max-app`, напр. `https://exampleprojectsail.fxtun.dev` |
| `MAX_PUBLIC_APP_URL` | Явный публичный origin для asset/API при `APP_URL=localhost` (по умолчанию — из `MAX_WEBHOOK_URL`) |
| `MAX_MINI_APP_URL` | URL для кнопки `open_app` в сообщениях (если пуст — `MAX_PUBLIC_APP_URL` + `/max-app` или `https://max.ru/{username}`) |
| `MAX_UI_STAND_GREETING` | Текст приветствия UI Stand |
| `MAX_UI_STAND_MINI_APP_BUTTON_TEXT` | Подпись кнопки mini-app в UI Stand (по умолчанию «Заказ еды») |
| `MAX_UI_STAND_CHAT_IDS` | Chat ID для тестовой рассылки (через запятую) |
| `MAX_UI_STAND_USER_IDS` | User ID для тестовой рассылки (через запятую) |
| `MAX_MINIAPP_AUTH_DATE_MAX_AGE_SECONDS` | Допустимый возраст `auth_date` в initData (по умолчанию 86400) |
| `MAX_MINIAPP_TOKEN_TTL_SECONDS` | TTL Bearer-токена Sanctum (по умолчанию 86400) |
| `MAX_RATE_LIMIT_RETRY_MAX` | Повторы при 429 Bot API |
| `MAX_RATE_LIMIT_RETRY_DELAY_MS` | Задержка между повторами при 429 |
| `MAX_ATTACHMENT_NOT_READY_RETRY_MAX` | Повторы при «attachment not ready» |
| `MAX_ATTACHMENT_NOT_READY_RETRY_DELAY_MS` | Задержка между такими повторами |

### Прочие

| Переменная | Назначение |
|---|---|
| `DB_*` | MySQL (`host.docker.internal`, БД `sail_db`) |
| `LOG_STACK` | По умолчанию `single,messMax` — события MAX в `storage/logs/messMax.log` |
| `REDIS_HOST` | `redis` (кэш/очереди в compose) |

## HTTPS-туннель на порт 8083

MAX требует **HTTPS:443** для webhook и mini-app. В dev нужен публичный туннель на `SERVICE_C_PORT` (по умолчанию **8083**).

> **fxTunnel и mini-app:** `/max-app` всегда отдаётся как `text/html` — иначе web.max.ru показывает сырой HTML вместо страницы. Если в MAX появляется «Dev Tunnel Warning», используйте VPS-туннель (`./scripts/vps-tunnel.sh`) или cloudflared (`./scripts/cloudflared-tunnel.sh`). Webhook (POST) interstitial fxTunnel не затрагивает.

> **APP_URL:** только корень домена (`https://sub.fxtun.dev`), **не** `.../max-app`. Иначе CSS/JS будут по пути `/max-app/build/...` и не загрузятся.

При запросах с хоста туннеля `AppServiceProvider` форсирует HTTPS и корневой URL из `APP_URL` или `MAX_PUBLIC_APP_URL` / origin `MAX_WEBHOOK_URL`.

## Особенности MAX PC (desktop)

Проверено на Max PC (QtWebEngine, `WebApp.platform === 'desktop'`):

| Сценарий | Поведение |
|---|---|
| Первое открытие mini-app | Работает после `WebApp.ready()` (вызывается из inline-boot в `max-app.blade.php`, когда `document.visibilityState === 'visible'`) |
| Закрытие через **кнопку «Назад»** в шапке Max → повторное открытие | Работает: вызывается `WebApp.close()`, host корректно создаёт новую сессию |
| Закрытие через **нижний трей** Max → повторное открытие из трея | **Не работает** без перезапуска Max: host не вызывает `WebApp.close()`, webview не перезагружается, JS не получает lifecycle-событий (`visibilitychange`, `pagehide`, смена размеров окна) |

**Причина:** ограничение клиента Max PC, а не mini-app или backend. Из JavaScript нельзя надёжно детектировать «свернули в трей» и принудительно восстановить сессию.

**Рекомендации для QA и пользователей desktop:**

- Закрывать mini-app кнопкой **«Назад»** в шапке Max (на экране ресторанов BackButton привязан к `WebApp.close()`).
- Если после закрытия через трей повторное открытие «зависло» — перезапустить Max PC или открыть mini-app из чата с ботом заново.
- Для багрепорта в Max: desktop не шлёт bridge-события при сворачивании в трей и не перезагружает webview при повторном клике по иконке в трее.

Реализация boot и закрытия: `resources/views/max-app.blade.php` (inline), `resources/js/max-app/bridge/maxBridge.js` (`closeMaxApp`).

В РФ **ngrok**, **VK Tunnel** и **cloudflared Quick Tunnel** (`trycloudflare.com`) часто недоступны. Рекомендуется **fxTunnel** на **fxtun.dev**:

```bash
curl -fsSL https://fxtun.dev/install.sh | sh
export FXTUN_TOKEN=sk_...   # https://fxtun.dev → личный кабинет
./scripts/fxtun-exampleprojectsail.sh run
```

Зарезервированный субдомен проекта: **`exampleprojectsail.fxtun.dev`** (скрипт `scripts/fxtun-exampleprojectsail.sh`).
Полный цикл подготовки: `./scripts/setup-max-fxtun.sh`.

Запасной вариант — **cloudflared** (если `api.trycloudflare.com` открывается, или через VPN):

```bash
./scripts/cloudflared-tunnel.sh install
./scripts/cloudflared-tunnel.sh service-c
```

После запуска туннеля укажите в `service-c/.env`:

```env
APP_URL=https://exampleprojectsail.fxtun.dev
MAX_WEBHOOK_URL=https://exampleprojectsail.fxtun.dev/api/webhooks/max
MAX_BOT_USERNAME=<username из max:bot:info>
# MAX_MINI_APP_URL можно не задавать — см. MaxOpenAppTargetResolver
```

Получить `MAX_BOT_USERNAME` и `MAX_BOT_USER_ID`:

```bash
docker compose exec -T service-c php artisan max:bot:info
```

**Кабинет MAX** → Чат-боты → ваш бот → **Чат-бот и мини-приложение** → URL мини-приложения:

`https://exampleprojectsail.fxtun.dev/max-app`

Кнопка «Заказ еды» (`open_app`) в сообщении использует `web_app` = `MAX_MINI_APP_URL` (или URL из туннеля) и `contact_id` = `MAX_BOT_USER_ID`. **После смены .env отправьте сообщение заново** — `php artisan max:ui-stand:send`; старые кнопки в чате не обновляются.

Проверка:

```bash
docker compose exec -T service-c php artisan max:miniapp:verify
```

> **При перезапуске туннеля** URL меняется — обновите значения в `.env` и в **кабинете MAX**, затем снова выполните `max:webhook:subscribe`.

## nginx-gateway: webhook без auth

В `nginx-gateway/nginx.conf` webhook вынесен в отдельный `location` **без** `auth_request` — MAX шлёт POST без Bearer-токена gateway:

```nginx
location = /api/c/webhooks/max {
    proxy_pass http://service-c:8000/api/webhooks/max;
    # без auth_request
}
```

Остальные маршруты `/api/c/*` по-прежнему защищены gateway auth. Для prod/dev с туннелем на `:8083` webhook идёт напрямую в service-c; gateway location нужен для локальной проверки через `:8080`.

## API (кратко)

### Публичные

| Метод | Путь | Описание |
|---|---|---|
| `POST` | `/api/webhooks/max` | Входящие события MAX (`message_callback`, `bot_started`) |
| `POST` | `/api/max/auth` | `{ "init_data": "..." }` → `{ token, token_type, expires_in, user }` |

### Food API (`Authorization: Bearer <token>`)

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/api/food/restaurants` | Список активных ресторанов |
| `GET` | `/api/food/restaurants/{id}/menu` | Категории и блюда (с `image_url`) |
| `GET` | `/api/food/cart` | Текущая корзина (`draft`); поля `items_total`, `delivery_applicable`, `delivery_cost`, `total`, `delivery_address`, `customer_category` |
| `PATCH` | `/api/food/cart` | Сохранить адрес доставки `{ delivery_address }` (string, max 1000) |
| `POST` | `/api/food/cart/items` | Добавить позицию `{ dish_id, quantity }` |
| `PATCH` | `/api/food/cart/items/{id}` | Изменить количество `{ quantity }` |
| `DELETE` | `/api/food/cart/items/{id}` | Удалить позицию |
| `POST` | `/api/food/orders/submit` | Оформить заявку (`draft` → `submitted`); адрес обязателен, иначе 422 |

Через gateway: префикс `/api/c`, например `GET /api/c/food/restaurants` (требует gateway auth; для mini-app используйте прямой `:8083` или туннель).

### Только local/testing

| Метод | Путь | Middleware | Описание |
|---|---|---|---|
| `GET` | `/api/max/me` | `max.miniapp.auth` | `{ max_user_id }` — отладка mini-app auth |
| `GET` | `/api/data` | `trust.gateway` | `{ user: { id } }` — отладка gateway auth |

## Artisan-команды MAX

```bash
docker compose exec -T service-c php artisan max:bot:info            # профиль бота (username, user_id)
docker compose exec -T service-c php artisan max:webhook:subscribe   # подписка webhook
docker compose exec -T service-c php artisan max:webhook:status      # статус подписок
docker compose exec -T service-c php artisan max:webhook:clean       # очистка dev-туннелей (.trycloudflare.com)
docker compose exec -T service-c php artisan max:miniapp:verify      # проверка URL mini-app
docker compose exec -T service-c php artisan max:ui-stand:send         # приветствие + кнопки
```

Лог MAX: `storage/logs/messMax.log` (канал `messMax`, без токена бота в записи). Дополнительно логируются запросы к `/max-app` и `/api/max/auth` (`MaxMiniAppAccessLogger`).

## Чеклист Manual QA (кабинет MAX)

Пошаговая ручная проверка перед демо или при смене dev-туннеля.

| Шаг | Действие | Ожидаемый результат |
|---|---|---|
| 1 | `docker compose up -d service-c` | Контейнер healthy, `http://localhost:8083` отвечает |
| 2 | `php artisan migrate` + `db:seed` (если БД пустая) | Демо-рестораны в списке |
| 3 | `./scripts/fxtun-exampleprojectsail.sh run` | Туннель на `exampleprojectsail.fxtun.dev` |
| 4 | В `service-c/.env`: токен, секрет, `APP_URL`, `MAX_WEBHOOK_URL`, `MAX_BOT_USERNAME` | `max:bot:info` для username и `MAX_BOT_USER_ID` |
| 5 | **Кабинет MAX** → URL мини-приложения = `https://exampleprojectsail.fxtun.dev/max-app` | Сохранено |
| 6 | `docker compose exec -T service-c php artisan max:webhook:subscribe` | Подписка на `message_callback`, `bot_started`; проба URL OK |
| 7 | Открыть mini-app в MAX (чат с ботом → mini-app) | Загружается SPA «Заказ еды», список ресторанов |
| 8 | Выбрать ресторан → меню → «В корзину» → корзина → «Оформить заявку» | Заявка создана, экран подтверждения |
| 9 | `docker compose exec -T service-c php artisan max:ui-stand:send` (опционально) | В MAX: приветствие + кнопки «да» / «нет» / «Заказ еды» |
| 10 | Нажать кнопку в MAX (UI Stand) | В `messMax.log`: событие callback, ответ «да» или «нет» |
| 11 | `docker compose exec -T service-c tail -f storage/logs/messMax.log` | События webhook/стенда без утечки токена |

### Пример команд (полный цикл)

```bash
# Подготовка (сборка, проверка .env)
./scripts/setup-max-fxtun.sh

# Терминал 1 — туннель (service-c должен быть запущен)
export FXTUN_TOKEN=sk_...
./scripts/fxtun-exampleprojectsail.sh run

# Терминал 2 — после настройки .env и кабинета MAX
docker compose exec -T service-c php artisan migrate --force
docker compose exec -T service-c php artisan db:seed
docker compose exec -T service-c php artisan max:webhook:subscribe
docker compose exec -T service-c php artisan max:ui-stand:send

# Лог стенда
docker compose exec -T service-c tail -f storage/logs/messMax.log
```

### Что не проверяется вручную в MVP

- Онлайн-оплата и доставка с трекингом
- Интеграция food API через gateway BFF (`main-app`)
- Стабильный URL при выключенном ПК (dev-only; prod — VPS 24/7)

## Тесты

```bash
docker compose exec -T service-c php artisan test
# или (с пересозданием sail_db_testing и миграциями всех сервисов)
./scripts/test-services.sh service-c
```

Тестовая БД: **`sail_db_testing`** (см. `phpunit.xml`, `.env.testing`).

| Область | Тесты |
|---|---|
| initData validator | `tests/Unit/MaxWebAppInitDataValidatorTest.php` |
| mini-app auth | `tests/Feature/MaxAuthControllerTest.php`, `tests/Unit/AuthenticateMaxMiniAppTest.php` |
| Food API / cart / orders | `tests/Feature/FoodRestaurantApiTest.php`, `FoodCartApiTest.php`, `FoodOrderApiTest.php`, `tests/Unit/CartServiceTest.php` |
| MAX webhook / UI Stand | `tests/Feature/MaxWebhookControllerTest.php`, `tests/Unit/MaxCallbackHandlerTest.php`, `MaxUiStandGreetingSenderTest.php`, `MaxUiStandSendCommandTest.php`, `MaxWebhookUpdateRouterTest.php`, `MaxWebhookSubscriberTest.php`, `MaxWebhookSubscribeCommandTest.php` |
| open_app / tunnel context | `tests/Unit/MaxOpenAppTargetResolverTest.php`, `MaxAppRequestContextTest.php`, `MaxMiniAppAccessLoggerTest.php` |
| `/max-app` route | `tests/Feature/MaxAppRouteTest.php` |
| Gateway auth | `tests/Unit/TrustGatewayAuthTest.php`, `EloquentGatewayUserResolverTest.php` |
| Webhook secret | `tests/Unit/VerifyMaxWebhookSecretTest.php` |
