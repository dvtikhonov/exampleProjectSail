# yandex-parser

Внутренний микросервис **Node.js + Playwright + Express** для работы с Яндекс.Картами. Доступен только из Docker-сети `internal`; с хоста порт не публикуется.

**Потребитель:** [`service-d`](../service-d/README.md) — Laravel + Vue SPA: организации и отзывы Яндекс.Карт.

## Разделение ответственности: yandex-parser ↔ service-d

`yandex-parser` — **stateless-адаптер к Яндекс.Картам**: открывает страницу в Chromium, собирает данные, возвращает JSON. Не знает о пользователях, сессиях и БД.

`service-d` — **оркестратор и публичный API**: валидирует ввод, вызывает парсер, интерпретирует `/resolve`, сохраняет результат `/sync-reviews`, отдаёт данные SPA.

| Область | `yandex-parser` | `service-d` |
|---|---|---|
| Публичный HTTP API | ❌ | ✅ `/api/*`, Vue SPA |
| Авторизация / БД / очередь | ❌ | ✅ Sanctum, MySQL, `service-d-queue` |
| Ввод «сайт + город» / построение search URL | ❌ | ✅ `OrganizationSearchInputValidator` |
| Сессия resolve и confirm кандидата | ❌ | ✅ cache + `OrganizationConfirmService` |
| Браузер, антибот, скролл | ✅ | ❌ |
| `POST /resolve` → сырой collect | ✅ | ❌ (только HTTP-клиент) |
| `POST /resolve` → `candidates[]` | ❌ **намеренно** | ✅ `OrganizationCandidateBuilder` |
| `POST /sync-reviews` → `org` + `reviews` | ✅ парсинг страницы | ❌ (только маппинг DTO → Eloquent) |
| `sync_status`, пагинация отзывов для UI | ❌ | ✅ |

### Асимметрия эндпоинтов

| Эндпоинт | Ответственность `yandex-parser` | Ответственность `service-d` |
|---|---|---|
| `/resolve` | Навигация, перехват JSON, `dom_harvest`, `page_meta` — **без** списка кандидатов | Сборка, merge, dedupe, фильтр по уточнению, `session_id` |
| `/sync-reviews` | Открытие страницы отзывов, извлечение `org` и `reviews` из DOM/API | `OrganizationSyncService`: upsert метаданных, `replaceForOrganization`, статусы |

**Не добавлять в `yandex-parser`:** выбор кандидата пользователем, кэш сессий, запись в MySQL, Sanctum, бизнес-правила «один user — одна org».

**Не добавлять в `service-d`:** Playwright, прямую работу с DOM Яндекса, скролл отзывов — только HTTP-вызовы через `PlaywrightYandexMapsClient`.

### Поток данных

```text
resolve
───────
Клиент → service-d POST /api/organization/resolve
       → service-d: resolverUrl из ввода пользователя
       → yandex-parser POST /resolve          (сырой collect)
       → service-d OrganizationCandidateBuilder
       → JSON: session_id + candidates

sync
────
Клиент → service-d POST /api/organization/confirm
       → очередь SyncYandexOrganizationReviewsJob (service-d-queue)
       → yandex-parser POST /sync-reviews     (готовые org + reviews)
       → service-d: БД, sync_status
```

Подробнее про публичный API, SPA, очередь и **подход к парсингу**: [`service-d/README.md`](../service-d/README.md#парсинг).

## Назначение

Сервис выполняет **браузерный сбор данных** с yandex.ru/maps:

| Задача | Эндпоинт | Кто интерпретирует результат |
|--------|----------|------------------------------|
| Поиск организации по URL Яндекс.Карт | `POST /resolve` | `service-d` (сборка кандидатов, фильтр по уточнению) |
| Загрузка метаданных и отзывов выбранной организации | `POST /sync-reviews` | `yandex-parser` парсит страницу; `service-d` сохраняет в БД |

Playwright открывает страницу, имитирует движение мыши (`humanMouseJiggle`), перехватывает JSON из XHR/fetch и при необходимости скроллит выдачу или список отзывов. Парсинг кандидатов из сырого collect **не входит** в этот сервис — см. раздел [**Парсинг**](../service-d/README.md#парсинг) в `service-d`.

## HTTP API

### `GET /health`

```json
{ "status": "ok" }
```

### `POST /resolve` — сырой collect страницы

**Запрос:**

```json
{ "url": "https://yandex.ru/maps/?text=invitro%20новокузнецк" }
```

URL должен указывать на домены Яндекс.Карт (`yandex.ru`, `yandex.com`, `yandex.kz`, `yandex.com.tr`).

**Ответ** (без поля `candidates`):

```json
{
  "resolved_url": "https://yandex.ru/maps/...",
  "is_direct_org": false,
  "direct_org_id": null,
  "network_payloads": [{ "...": "..." }],
  "dom_harvest": [
    {
      "href": "/maps/org/invitro/11527230587/",
      "link_text": "Invitro",
      "card_text": "ул. Тореза, 61 ... 24 отзыва",
      "rating_aria_label": "рейтинг 4,4",
      "meta_text": "ул. Тореза, 61, Новокузнецк"
    }
  ],
  "page_meta": {
    "title": "Invitro — Яндекс Карты",
    "header_text": "Invitro",
    "address_text": "ул. Тореза, 61, Новокузнецк"
  }
}
```

| Поле | Описание |
|------|----------|
| `resolved_url` | Финальный URL после редиректов |
| `is_direct_org` | `true`, если открыта карточка одной организации (без скролла выдачи) |
| `direct_org_id` | Числовой id из URL при `is_direct_org` |
| `network_payloads` | Тела JSON, перехваченные из сетевых запросов |
| `dom_harvest` | Фрагменты ссылок/карточек со страницы поиска или прямой карточки |
| `page_meta` | `title`, `header_text`, `address_text` без бизнес-интерпретации |

Типы: `src/types.ts` (`ResolveCollectResponseBody`, `DomOrgHarvest`, `PageMeta`).

### `POST /sync-reviews` — метаданные и отзывы

**Запрос:**

```json
{
  "org_id": "1038900970",
  "canonical_url": "https://yandex.ru/maps/org/invitro/1038900970/"
}
```

**Ответ:**

```json
{
  "org": {
    "org_id": "1038900970",
    "name": "Invitro",
    "address": "ул. Энтузиастов, 32, Новокузнецк",
    "average_rating": 4.68,
    "reviews_count": 120,
    "ratings_count": 682,
    "canonical_url": "https://yandex.ru/maps/org/invitro/1038900970/"
  },
  "reviews": [
    {
      "external_id": "...",
      "author_name": "...",
      "published_at": "2025-01-15T10:00:00.000Z",
      "text": "...",
      "rating": 5
    }
  ]
}
```

Вызывается из `PlaywrightYandexMapsClient::syncReviews()` при обработке `SyncYandexOrganizationReviewsJob`.

### Ошибки

```json
{ "error": "validation_error", "message": "..." }
```

Коды: `400` (валидация), `422` (некорректный URL или `org_id`), `500` (`parser_error`).

## Переменные окружения

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `PORT` | `3000` | HTTP-порт |
| `HEADLESS` | `true` | Chromium без UI |
| `MOUSE_JIGGLE_MIN_PX` | `10` | Мин. смещение мыши (антибот) |
| `MOUSE_JIGGLE_MAX_PX` | `80` | Макс. смещение мыши |
| `RESOLVE_CANDIDATE_LIMIT` | `30` | Глубина скролла на странице поиска (`ceil(limit/5)` шагов) |
| `SYNC_MAX_IDLE_ITERATIONS` | `15` | Остановка скролла отзывов после N «пустых» итераций |
| `SYNC_SCROLL_DELAY_MS` | `800` | Пауза между шагами скролла |
| `NAVIGATION_TIMEOUT_MS` | `60000` | Таймаут навигации Playwright |

Лимит кандидатов **после** merge/dedupe задаётся в `service-d`: `YANDEX_PARSER_RESOLVE_CANDIDATE_LIMIT`.

### Отладка (опционально)

В `docker-compose.yml` для локальной диагностики:

| Переменная | Описание |
|------------|----------|
| `DEBUG_ORG_IDS` | Список `org_id` через запятую — для org из списка пишутся NDJSON-дампы |
| `PARSER_DEBUG_DUMP_DIR` | Каталог дампов (по умолчанию `/app/debug-dumps`, смонтирован в `yandex-parser/debug-dumps/`) |
| `DEBUG_LOG_PATH` | Дополнительный лог в workspace |

Скрипт ручного вызова sync с дампом: [`scripts/debug-dump-yandex-org.sh`](../scripts/debug-dump-yandex-org.sh).

## Разработка

```bash
cd yandex-parser
npm install
npm run dev      # tsx watch
npm test         # vitest (тесты также выполняются при docker build)
npm run build    # tsc → dist/
```

Требуется Node.js ≥ 20. Playwright 1.61.0 (см. `Dockerfile` и `package.json`).

## Docker

Сервис `yandex-parser` в корневом [`docker-compose.yml`](../docker-compose.yml). `service-d` и `service-d-queue` подключаются через `YANDEX_PARSER_URL=http://yandex-parser:3000`.

```bash
docker compose build yandex-parser
docker compose up -d yandex-parser
```

После изменений в `src/` **обязательна пересборка образа** — исходники в runtime-контейнер не монтируются.

Проверка health:

```bash
docker compose exec -T yandex-parser node -e \
  'fetch("http://127.0.0.1:3000/health").then(r=>r.text()).then(console.log)'
```

Краткий smoke-тест `/resolve` и end-to-end через Laravel — в разделе «Ручная проверка» [`service-d/README.md`](../service-d/README.md).

## Структура `src/` (по зонам ответственности)

| Каталог / файл | Эндпоинт | Назначение |
|----------------|----------|------------|
| `index.ts` | оба | Express-приложение, маршруты, валидация входа |
| `browser.ts`, `humanMouseJiggle.ts` | оба | Playwright: контекст, навигация, антибот |
| `resolve/` | `/resolve` | Collect: `resolveOrganization`, `domHarvest` — **только сырьё** |
| `sync/syncReviews.ts` | `/sync-reviews` | Парсинг страницы отзывов → готовые `org` + `reviews` |
| `utils/jsonExtract.ts` | в основном `/resolve` | Перехват и обход JSON из сети |
| `utils/orgExtract.ts`, `reviewExtract.ts` | `/sync-reviews` | Извлечение полей org/review (не для candidate builder) |
| `utils/yandexUrl.ts` | оба | Нормализация и валидация URL Яндекс.Карт |
| `types.ts` | оба | Контракты HTTP API (синхронизировать с DTO в `service-d`) |
| `config.ts` | оба | Переменные окружения |

Логику candidate builder (`OrganizationCandidateBuilder`, merge по `org_id` из выдачи) **не** переносить в `resolve/` — она остаётся в `service-d`.

## Замечания

- Прямые URL карточки (`/maps/org/.../ID/`) в headless-режиме иногда редиректятся — для smoke-тестов надёжнее поисковые URL.
- DOM и внутренние API Яндекс.Карт меняются; следите за ошибками sync в production.
- При изменении формата ответа `/resolve` деплойте `yandex-parser` и `service-d` **вместе**.
