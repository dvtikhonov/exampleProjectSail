# QA: тест-кейсы интеграции с MAX (service-c)

Ручные тест-кейсы приёмки **мини-приложения**, **webhook**, **уведомлений Bot API** и **админки Food** в мессенджере MAX.

| | |
|---|---|
| Сервис | `service-c` |
| Платформа | [dev.max.ru/docs](https://dev.max.ru/docs) · [Bot API](https://dev.max.ru/docs-api) |
| Реализация | [service-c/README.md](../README.md) |
| Ops Telegram (паритет клиента) | [TELEGRAM_MINI_APP_OPS.md](./TELEGRAM_MINI_APP_OPS.md) |
| Каталог скриптов | [docs/scripts.md](../../docs/scripts.md) |

**Миграции** на shared/prod — только после явного согласия (`.cursor/rules/migration.mdc`).

---

## Паспорт документа

| Поле | Значение |
|---|---|
| Цель | Приёмка интеграции service-c ↔ MAX (Mini App + бот) |
| Тип | Manual QA / регрессия / smoke |
| Стенд | local (туннель HTTPS) или VPS; `MAX_MESSENGER_DRIVER=http` |
| Mini App | `GET /max-app` |
| Auth | `POST /api/max/auth` (`initData` → Bearer) |
| Webhook | `POST /api/webhooks/max` (заголовок `X-Max-Bot-Api-Secret`) |
| Лог | `storage/logs/max_log-YYYY-MM-DD.log` (канал `max_log`) |
| Bot API | `platform-api.max.ru` |

**Как заполнять «Факт»:** `OK` / `FAIL` + дата, стенд, скрин или ссылка на строку `max_log`.  
**Приоритет:** `P0` — блокер релиза интеграции; `P1` — обязательная регрессия; `P2` — желательно / не-prod.

---

## Роли и тестовые данные

| Роль | Как получить | Для кейсов |
|---|---|---|
| Клиент MAX | Обычный пользователь бота / Mini App | TC-MAX-2xx, 3xx |
| `address_reviewer` | `php artisan max:food-admin:assign <max_user_id> address_reviewer` | TC-MAX-4xx |
| `composition_reviewer` | `… composition_reviewer` | TC-MAX-4xx |
| `menu_manager` | `… menu_manager` | TC-MAX-5xx |
| `max_manager` | `… max_manager` | TC-MAX-6xx |
| Без ролей | Новый user без assign | RBAC |

Демо local (без WebView): `MAX_LOCAL_DEV_INIT_DATA=true`, `MAX_LOCAL_DEV_USER_ID=1001…1004` (`config/max.php`). **На prod флаг выключен.**

---

## 0. Предусловия стенда

| ID | Проверка | Ожидание | Pri | Факт |
|---|---|---|---|---|
| PRE-01 | `docker compose up -d service-c` (+ `queue:work`, если jobs) | Healthy; `http://localhost:8083` отвечает | P0 | |
| PRE-02 | `MAX_MESSENGER_DRIVER=http`, задан `MAX_BOT_ACCESS_TOKEN` | Реальные вызовы Bot API (не `null`) | P0 | |
| PRE-03 | `MAX_WEBHOOK_URL` (HTTPS), `MAX_WEBHOOK_SECRET`, `MAX_BOT_USERNAME`, `MAX_BOT_USER_ID` | `php artisan max:bot:info` → username / user_id; secret не в git | P0 | |
| PRE-04 | `MAX_MINI_APP_URL` = `{origin}/max-app` (или пусто → из webhook URL) | Совпадает с URL в **кабинете MAX** | P0 | |
| PRE-05 | `MAX_UI_STAND_CHAT_IDS` / `MAX_UI_STAND_USER_IDS` | Home_chat / лички: заказы, чат, UI Stand, MaxIncomingRelay | P0 | |
| PRE-06 | `MAX_REPORT_CHAT_IDS` / `MAX_REPORT_USER_IDS` | Отдельный чат: «тест бот», меню (**не** смешивать с UI Stand) | P1 | |
| PRE-07 | Бот в целевых чатах; пользователь открывал бота / Mini App | Исходящие доходят; сбой MAX **не** откатывает заказ | P0 | |
| PRE-08 | Сборка фронта при необходимости | `./scripts/build-max-app.sh` → `public/max-build/` | P1 | |

Smoke без WebView:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' "${MAX_ORIGIN}/max-app"   # → 200
docker compose exec -T service-c php artisan max:miniapp:verify
docker compose exec -T service-c php artisan max:webhook:status
```

---

## 1. Кабинет MAX и webhook

### TC-MAX-101 — URL Mini App в кабинете

| | |
|---|---|
| Приоритет | P0 |
| Предусловия | PRE-01…PRE-04 |
| Шаги | 1. Кабинет MAX → URL мини-приложения = `{origin}/max-app`. 2. Сохранить. 3. Открыть Mini App из чата бота. |
| Ожидание | URL сохранён; SPA «Заказ еды» загружается. |
| Факт | |

### TC-MAX-102 — Подписка webhook

| | |
|---|---|
| Приоритет | P0 |
| Шаги | `docker compose exec -T service-c php artisan max:webhook:subscribe` |
| Ожидание | Подписка на `message_callback`, `bot_started`, `message_created`; проба URL OK. |
| Факт | |

### TC-MAX-103 — Секрет webhook

| | |
|---|---|
| Приоритет | P0 |
| Шаги | 1. `POST /api/webhooks/max` без секрета / с неверным. 2. С валидным `X-Max-Bot-Api-Secret`. |
| Ожидание | 1 → `401`/`403`. 2 → `200`. |
| Факт | |

### TC-MAX-104 — Смена туннеля / origin

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Обновить `.env` + кабинет MAX → снова `max:webhook:subscribe`. |
| Ожидание | Старые URL не остаются в подписке; mini-app открывается с нового origin. |
| Факт | |

### TC-MAX-105 — Очистка stale tunnel (dev)

| | |
|---|---|
| Приоритет | P2 |
| Шаги | При необходимости `php artisan max:webhook:clean`. |
| Ожидание | Убраны подписки на `.trycloudflare.com` и аналоги. |
| Факт | |

---

## 2. Авторизация Mini App

### TC-MAX-201 — Happy path auth

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Открыть Mini App из MAX. |
| Ожидание | `POST /api/max/auth` → 200; SPA; запись в `max_users` (`messenger=max`); Bearer в `sessionStorage` (`max_miniapp_token`). |
| Факт | |

### TC-MAX-202 — Битый / протухший initData

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Подмена или устаревший `auth_date` (> `MAX_MINIAPP_AUTH_DATE_MAX_AGE_SECONDS`). |
| Ожидание | Ошибка auth в UI; без Bearer API → `401`. |
| Факт | |

### TC-MAX-203 — Локальный браузер (только local)

| | |
|---|---|
| Приоритет | P1 |
| Шаги | `MAX_LOCAL_DEV_INIT_DATA=true`, user `1001…1004` → `http://localhost:8083/max-app`. |
| Ожидание | Демо-профиль из `config/max.php`. На prod флаг **выключен**. |
| Факт | |

### TC-MAX-204 — TTL Bearer

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Дождаться > `MAX_MINIAPP_TOKEN_TTL_SECONDS` или сбросить токен; вызвать API. |
| Ожидание | `401`; нужен повторный auth. |
| Факт | |

### TC-MAX-205 — Лог доступа без секретов

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Открыть `/max-app` и пройти auth; открыть `max_log`. |
| Ожидание | События доступа есть; **нет** `MAX_BOT_ACCESS_TOKEN` / webhook secret / полного initData. |
| Факт | |

---

## 3. Клиентский поток (заказ еды)

### TC-MAX-301 — Список ресторанов

| | |
|---|---|
| Приоритет | P0 |
| Роль | Клиент |
| Шаги | После auth открыть список ресторанов. |
| Ожидание | Активные рестораны; пустой список / ошибка — понятный UI. |
| Факт | |

### TC-MAX-302 — Меню и картинки

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Открыть меню; проверить картинки блюд. |
| Ожидание | Категории, цены, комбо (если доступно); `<img>` same-origin `/api/food/dishes/{id}/image` **без** Bearer → 200. |
| Факт | |

### TC-MAX-303 — Корзина

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Добавить позиции, изменить qty, очистить. |
| Ожидание | Итог, доставка по категории, подсказка следующего порога (`amount_to_next_tier`). |
| Факт | |

### TC-MAX-304 — Адрес и дата доставки

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Задать/проверить адрес в шапке; открыть корзину / модалку. |
| Ожидание | `delivery_date` = «Блюда на» из графика доступности; в UI дата видна. |
| Факт | |

### TC-MAX-305 — Оформление заявки + уведомление UI Stand

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Модалка → «Оформить». Проверить Home_chat / `MAX_UI_STAND_*`. |
| Ожидание | Заявка `pending_review`; экран подтверждения; в UI Stand — новая заявка (+ дата доставки); клиенту — «принят на рассмотрение». |
| Факт | |

### TC-MAX-306 — Сбой MAX API при notify не ломает заказ

| | |
|---|---|
| Приоритет | P0 |
| Шаги | (опц.) временно неверный `MAX_BOT_ACCESS_TOKEN` → оформить заказ → вернуть токен. |
| Ожидание | Заказ **создан** в БД; ошибка только в `max_log`. |
| Факт | |

### TC-MAX-307 — Мои заказы

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Список → деталь заказа. |
| Ожидание | Статусы review; бейдж `unread_count` при непрочитанных. |
| Факт | |

### TC-MAX-308 — Чат заказа (клиент → админ)

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Отправить сообщение в чат заказа. |
| Ожидание | В `MAX_UI_STAND_*` — «поступило сообщение» + текст; клиенту своё сообщение **не** дублируется в личку. |
| Факт | |

### TC-MAX-309 — Кнопка open_app «Заказ еды»

| | |
|---|---|
| Приоритет | P0 |
| Шаги | `php artisan max:ui-stand:send` → нажать «Заказ еды». |
| Ожидание | Открывается Mini App (`contact_id` = `MAX_BOT_USER_ID`). |
| Факт | |

### TC-MAX-310 — Deep link в чат заказа

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Из уведомления MAX нажать «Открыть заказ №N» (`payload` `order_{id}_chat`). |
| Ожидание | Mini App открывается на чате/карточке заказа (`start_param`). |
| Факт | |

---

## 4. Админка: проверка заказа

Роли: `address_reviewer`, `composition_reviewer`.

### TC-MAX-401 — Вход админа

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Пользователь с ролями → Mini App. |
| Ожидание | Admin shell; вкладка «Заказы». |
| Факт | |

### TC-MAX-402 — Очередь review

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Открыть список заказов на проверку. |
| Ожидание | Нет `draft_after_scanning` в общей очереди review. |
| Факт | |

### TC-MAX-403 — Адрес / оплата (approve / reject)

| | |
|---|---|
| Приоритет | P0 |
| Роль | `address_reviewer` |
| Шаги | Approve и отдельно reject этапа. |
| Ожидание | Этапы обновляются; клиент получает личное уведомление о результате. |
| Факт | |

### TC-MAX-404 — Состав: правка и решение

| | |
|---|---|
| Приоритет | P0 |
| Роль | `composition_reviewer` |
| Шаги | Правка qty / добавить / удалить / комбо → сохранить → approve/reject. |
| Ожидание | При правке клиенту: «заказ изменен»; итог проверки — в личку. |
| Факт | |

### TC-MAX-405 — Чат админ → клиент

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Ответ в чате заказа от админа. |
| Ожидание | Клиент: «В чат заказа №N поступило сообщение»; UI Stand — то же + текст. |
| Факт | |

### TC-MAX-406 — RBAC без роли

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Пользователь без admin-ролей → разделы/API админки. |
| Ожидание | UI скрыт / API `403`. |
| Факт | |

---

## 5. Админка: меню (`menu_manager`)

### TC-MAX-501 — CRUD категорий + offsets

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Создать/изменить категорию и `availability_offsets`. |
| Ожидание | Сохраняется; влияет на дату «Блюда на». |
| Факт | |

### TC-MAX-502 — CRUD блюд / фото / импорт

| | |
|---|---|
| Приоритет | P1 |
| Шаги | CRUD блюда, фото; опц. импорт XLS/XLSX. |
| Ожидание | Данные и комбо в категориях с флагом корректны. |
| Факт | |

### TC-MAX-503 — График доступности

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Sync `dish-availability-schedule` / проверка окна дат. |
| Ожидание | Окно дат синхронизировано с ожиданиями стенда. |
| Факт | |

### TC-MAX-504 — «тест бот» (не-prod)

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Кнопка «тест бот» в админке меню. |
| Ожидание | Сообщение в **`MAX_REPORT_*`**, не в UI Stand. |
| Факт | |

### TC-MAX-505 — «тест бот 2» (не-prod)

| | |
|---|---|
| Приоритет | P1 |
| Шаги | При заданных `MAX_UI_STAND_CHAT_IDS` нажать «тест бот 2». |
| Ожидание | Текст `тест бот 2` в UI Stand (+ кэш webhook); `MAX_REPORT_*` не затрагиваются. |
| Факт | |

---

## 6. Админка: `max_manager`

### TC-MAX-601 — Ручной заказ

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Выбрать клиента → меню/корзина → «Оформить». |
| Ожидание | `confirmed` / `is_manual`; UI Stand — новая заявка; менеджерам — «к исполнению»; оформившему — состав; клиенту confirm **не** уходит. |
| Факт | |

### TC-MAX-602 — Просмотр ручных / фильтры

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Фильтры период/статус (в т.ч. «Черновик после сканирования»). |
| Ожидание | `meta.total_amount`; деталь; чат read-only при `has_messages`. |
| Факт | |

### TC-MAX-603 — Draft после скана

| | |
|---|---|
| Приоритет | P1 |
| Шаги | «Выполнить» / «В корзину» / «Удалить» на `draft_after_scanning`. |
| Ожидание | Статусы/корзина меняются по действию. |
| Факт | |

### TC-MAX-604 — AI-доступ

| | |
|---|---|
| Приоритет | P1 |
| Шаги | «Вкл. доступ AI». |
| Ожидание | TTL ~30 мин; один активный; конфликт → понятная ошибка. |
| Факт | |

### TC-MAX-605 — Чат бота (Bot DM)

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Раздел «Чат бота» → ответ пользователю. |
| Ожидание | Сообщение уходит в MAX; запись в `max_bot_direct_messages`. |
| Факт | |

### TC-MAX-606 — Отчёты Food → MAX

| | |
|---|---|
| Приоритет | P2 |
| Шаги | Revenue / top-dishes / export «Отправить в MAX». |
| Ожидание | Данные на экране; export уходит в MAX по принятому сценарию (`MAX_REPORT_*` / job). |
| Факт | |

### TC-MAX-607 — Briskly smoke

| | |
|---|---|
| Приоритет | P2 |
| Шаги | Сессия → snapshot → match → approvals → apply. |
| Ожидание | UI не падает; ошибки sidecar понятны (отдельный контур). |
| Факт | |

---

## 7. Входящие события бота (MaxIncomingRelay / UI Stand)

### TC-MAX-701 — `bot_started`

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Старт диалога с ботом (/start или аналог). |
| Ожидание | Greeting / open_app по логике стенда; запись в `max_log`. |
| Факт | |

### TC-MAX-702 — `message_created` (личка)

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Написать текст боту в личку. |
| Ожидание | В Home_chat (`MAX_UI_STAND_CHAT_IDS`) — relay; при известном `max_users` — строка в `max_bot_direct_messages`; текст в `max_log`. |
| Факт | |

### TC-MAX-703 — `message_callback` «да» / «нет»

| | |
|---|---|
| Приоритет | P1 |
| Шаги | `max:ui-stand:send` → нажать «да» или «нет» в группе. |
| Ожидание | В `max_log`: `MAX button clicked`, answer; `chat_id` группы; кэш для «тест бот 2». |
| Факт | |

### TC-MAX-704 — Изоляция получателей Report vs UI Stand

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Сверить куда уходят заказы/чат/relay vs меню/«тест бот». |
| Ожидание | Заказы / чат / relay → **только** `MAX_UI_STAND_*`; меню / «тест бот» → `MAX_REPORT_*`. |
| Факт | |

Команды:

```bash
docker compose exec -T service-c php artisan max:ui-stand:send
docker compose exec -T service-c tail -f storage/logs/max_log-$(date +%Y-%m-%d).log
```

---

## 8. Негативные и безопасность

### TC-MAX-801 — API без Bearer

| | |
|---|---|
| Приоритет | P0 |
| Шаги | `GET /api/food/restaurants` без Authorization. |
| Ожидание | `401`. |
| Факт | |

### TC-MAX-802 — Telegram Mini App → admin API

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Клиент `/tg-app` с токеном Telegram → admin routes. |
| Ожидание | Forbidden (`forbid.telegram.miniapp`). |
| Факт | |

### TC-MAX-803 — Webhook secret в логах

| | |
|---|---|
| Приоритет | P0 |
| Шаги | Неверный/пустой секрет + просмотр `max_log`. |
| Ожидание | Отказ; в логе нет `MAX_BOT_ACCESS_TOKEN` / secret. |
| Факт | |

### TC-MAX-804 — Rate limit auth

| | |
|---|---|
| Приоритет | P1 |
| Шаги | Много `POST /api/max/auth` подряд. |
| Ожидание | Throttle срабатывает. |
| Факт | |

### TC-MAX-805 — Секреты не в UI / Network

| | |
|---|---|
| Приоритет | P0 |
| Шаги | DevTools Network + UI + `max_log` после happy path. |
| Ожидание | Нет bot token / webhook secret в ответах и логах. |
| Факт | |

---

## 9. Матрица уведомлений (регрессия)

| ID | Событие | Куда | Текст (смысл) | Pri | Факт |
|---|---|---|---|---|---|
| N-01 | Submit клиентского заказа | `MAX_UI_STAND_*` | Новая заявка (+ дата доставки) | P0 | |
| N-02 | Submit → клиент | Личка клиента | «Принят на рассмотрение» | P0 | |
| N-03 | Approve / reject этапов | Личка клиента | Результат проверки | P0 | |
| N-04 | Правка состава | Личка клиента | «Заказ изменен» | P0 | |
| N-05 | Сообщение в чате заказа | UI Stand (+ клиент при ответе админа) | «Поступило сообщение» | P0 | |
| N-06 | Ручной заказ | UI Stand + менеджеры + оформивший | Без confirm клиенту | P0 | |
| N-07 | Доступность меню (cron) | `MAX_REPORT_*` | «Доступно для заказов меню на …» | P1 | |
| N-08 | Личка боту | `MAX_UI_STAND_CHAT_IDS` | Relay входящего текста | P0 | |

Правило: ошибка отправки в MAX **не** откатывает бизнес-операцию (заказ / сообщение уже в БД).

---

## 10. Smoke-набор (15–20 мин) — P0

| # | Кейс | Факт |
|---|---|---|
| 1 | TC-MAX-102 + TC-MAX-201 → список ресторанов | |
| 2 | TC-MAX-305 → уведомление в Home_chat | |
| 3 | TC-MAX-403 (один этап) → личка клиенту | |
| 4 | TC-MAX-308 + TC-MAX-405 → UI Stand | |
| 5 | TC-MAX-702 → relay в Home_chat | |
| 6 | TC-MAX-703 → «да»/«нет» в `max_log` | |
| 7 | TC-MAX-205 / TC-MAX-805 → нет токена бота в логах | |

**Критерий приёмки smoke:** все 7 строк `OK`.

---

## Вне scope (не блокирует приёмку MAX-интеграции)

- Онлайн-оплата / платёжный шлюз (ручной этап `address_reviewer`)
- Трекинг курьера
- Полный E2E Briskly / SchedulePhoto / PhotoText (отдельные чеклисты модулей)
- Связка аккаунтов MAX ↔ Telegram
- Админка в Telegram Mini App (только клиент)
- Нагрузочный профиль (`service-h`, `max:load-test:*`)
- Отчёты sales outlets в `service-b` (`max_message`) — другой сервис

---

## Справка: env и команды

| Env | Назначение |
|---|---|
| `MAX_BOT_ACCESS_TOKEN` | Bot API + валидация `initData` |
| `MAX_BOT_USERNAME` / `MAX_BOT_USER_ID` | Профиль; `contact_id` для `open_app` |
| `MAX_WEBHOOK_URL` / `MAX_WEBHOOK_SECRET` | Входящий webhook |
| `MAX_MINI_APP_URL` | URL Mini App / кнопка |
| `MAX_MESSENGER_DRIVER` | `http` \| `null` |
| `MAX_UI_STAND_*` | Заказы, чат, greeting, relay |
| `MAX_REPORT_*` | «тест бот», меню, food report export |
| `MAX_MINIAPP_*` | TTL initData / Sanctum-токена |

```bash
docker compose exec -T service-c php artisan max:bot:info
docker compose exec -T service-c php artisan max:webhook:subscribe
docker compose exec -T service-c php artisan max:webhook:status
docker compose exec -T service-c php artisan max:miniapp:verify
docker compose exec -T service-c php artisan max:ui-stand:send
docker compose exec -T service-c php artisan max:food-admin:assign <max_user_id> <role>
```

Подготовка стенда: `./scripts/setup-max-fxtun.sh` или `./scripts/setup-max-vps.sh` (см. [docs/scripts.md](../../docs/scripts.md)).
