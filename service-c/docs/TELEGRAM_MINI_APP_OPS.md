# Ops: Telegram Mini App (`@obedov_bot`)

Чеклист выката клиентского Telegram Mini App, исходящих клиентских уведомлений и **входящей лички с ботом** (паритет MaxIncomingRelay / Bot DM).  
**Миграции на shared/prod — только после явного согласия** (см. `.cursor/rules/migration.mdc`).

| Параметр | Значение |
|---|---|
| Бот | [@obedov_bot](https://t.me/obedov_bot) |
| Mini App URL | `https://94-228-117-27.sslip.io/tg-app` |
| Auth API | `POST /api/telegram/auth` |
| Bot webhook | `POST /api/webhooks/telegram` |
| Миграция identity | `service-c/database/migrations/2026_10_19_000001_add_messenger_and_external_user_id_to_max_users_table.php` |

## 1. BotFather

В [@BotFather](https://t.me/BotFather) для `@obedov_bot`:

1. **Bot Settings → Menu Button** (или **/setmenubutton**): тип **Web App**.
2. URL Web App: `https://94-228-117-27.sslip.io/tg-app` (тот же, что `TELEGRAM_MINI_APP_URL`).
3. Токен бота (`/token` или существующий) сохранить для шага 2 — **не коммитить**.
4. **Main Mini App / Configure Mini App → Mode:** **Default** (не **Fullscreen**).  
   Fullscreen на Telegram Desktop (Windows) часто прижимает панель к правому краю экрана без title bar — окно нельзя перетащить. Клиентский код на desktop сам вызывает `exitFullscreen()`, но режим в BotFather тоже должен быть Default.

Проверка: в Telegram открыть бота → Menu Button открывает Mini App по HTTPS на `/tg-app`.  
На Desktop: отдельная панель с заголовком (можно перемещать), не «прилипшая» к правому краю монитора.

## 2. VPS: переменные и деплой

В `service-c/.env` на VPS (шаблон — `service-c/.env.example`):

| Переменная | Prod |
|---|---|
| `TELEGRAM_BOT_TOKEN` | токен из BotFather (**обязателен** для auth, `sendMessage`, webhook) |
| `TELEGRAM_BOT_USERNAME` | `obedov_bot` |
| `TELEGRAM_MINI_APP_URL` | `https://94-228-117-27.sslip.io/tg-app` |
| `TELEGRAM_WEBHOOK_URL` | `https://94-228-117-27.sslip.io/api/webhooks/telegram` |
| `TELEGRAM_WEBHOOK_SECRET` | случайная строка 1–256 `[A-Za-z0-9_-]` (заголовок `X-Telegram-Bot-Api-Secret-Token`) |
| `TELEGRAM_LOCAL_DEV_INIT_DATA` | `false` |

После правки `.env`:

1. Деплой service-c (workflow `.github/workflows/deploy.yml` или ручной sync на VPS).
2. Пересоздать/перезапустить контейнер `service-c` (и `service-c-queue`, если очередь отдельно), чтобы подтянуть env.
3. Убедиться, что nginx-gateway отдаёт `/tg-app` и `/api/telegram/` на service-c (уже в `nginx-gateway/nginx.conf`).
4. Сборка фронта: `./scripts/build-max-app.sh` (общий Vite entry `/max-build/` для `/max-app` и `/tg-app`).

Smoke без Telegram WebView: `curl -sS -o /dev/null -w '%{http_code}\n' https://94-228-117-27.sslip.io/tg-app` → `200`.

## 3. Первый контакт пользователя с ботом

`sendMessage` с `chat_id = external_user_id` работает только если пользователь хотя бы раз открыл бота / Mini App (иначе Telegram может ответить `403` вроде «bot was blocked» / «chat not found»). Ошибки логируются и **не** валят оформление заказа (как у MAX).

Перед проверкой notify: открыть [@obedov_bot](https://t.me/obedov_bot) → Mini App → авторизоваться (создаётся строка `max_users` с `messenger=telegram`).

## 4. Миграция `max_users` (только после согласия)

Файл: `2026_10_19_000001_add_messenger_and_external_user_id_to_max_users_table.php`  
Добавляет `messenger`, `external_user_id`, backfill для существующих MAX-пользователей, unique `(messenger, external_user_id)`.

| Контур | Когда |
|---|---|
| PHPUnit / `sail_db_testing` | Автоматически через `RefreshDatabase` — **не** считается прод-миграцией |
| Shared / production | **Только после явного согласия.** Учитывать все три каталога сервисов (правило `migration.mdc`). В GitHub Actions: `run_migrations=true` **только** когда согласовано |

**Не запускать** `php artisan migrate` / `deploy` с `run_migrations=true` «заодно» с деплоем кода без отдельного «да» на migrate.

После согласия на VPS (пример; выполнять только по подтверждению):

```bash
docker compose exec -T service-c php artisan migrate --force
```

(Либо полный job `run-migrations` в `deploy.yml` по всем сервисам — тоже только после согласия.)

## 5. Входящий webhook (личка с ботом → Home_chat + Bot DM)

Паритет с MAX `message_created` / MaxIncomingRelay:

1. В `.env` задать `TELEGRAM_WEBHOOK_URL` и `TELEGRAM_WEBHOOK_SECRET`.
2. Убедиться, что nginx отдаёт `POST /api/webhooks/telegram` на service-c **без** gateway Bearer (см. `nginx-gateway/nginx.conf`).
3. Зарегистрировать webhook:

```bash
docker compose exec -T service-c php artisan telegram:webhook:subscribe
```

4. Smoke: написать боту в личку → текст в Home_chat (`MAX_UI_STAND_CHAT_IDS`) и, если пользователь уже в `max_users` (`messenger=telegram`), запись в `max_bot_direct_messages`.
5. Ответ админа: раздел «Чат бота» в MAX Mini App → сообщение уходит через Telegram Bot API (`chat_id = external_user_id`).

## 6. Порядок выката (кратко)

1. BotFather Menu Button → `https://94-228-117-27.sslip.io/tg-app`
2. `TELEGRAM_BOT_TOKEN` + `TELEGRAM_MINI_APP_URL` (+ webhook URL/secret) в `service-c/.env` на VPS
3. Деплой кода **без** migrate (пока нет согласия)
4. Получить согласие → migrate (все согласованные сервисы / каталоги)
5. `php artisan telegram:webhook:subscribe`
6. Открыть бота в Telegram → smoke auth / заказ / клиентский notify / личка → Home_chat

## Вне scope этого чеклиста

- Связка аккаунтов MAX ↔ Telegram
- Админка в Telegram Mini App
