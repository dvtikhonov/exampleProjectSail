# briskly-sync (часть 3)

Node/TS пакет: HTTP-клиент Briskly (часть 2) + MCP + Cursor match orchestrator.

## Возможности

- **MCP `food-source`**: `list_source_lines` → HTTP к service-c admin source-lines
- **MCP `briskly-target`**: `list_items` / `get_item` / `update_item_price` / `list_categories` / `create_item`
- **Match**: `Agent.create` + `send` с промптом **только** из PHP `ComboCatalogPromptDto` (ENUM + PromptBuilder); Node не дублирует system-текст
- **Классификация 1D/2B**: `PriceDiffBuilder` + `VpsOnlyCreateBuilder`, cap 25, drop briskly-only
- **Apply**: детерминированный по approvals (без LLM)

## Команды

```bash
npm test
npm run match -- --fixture fixtures/match-run.json
npm run mcp:food-source
npm run mcp:briskly-target
npm run sidecar
npm run capture-token
npm run capture-token -- --write-env
```

## Env

См. `.env.example`. Секреты не коммитить.

- Live match: `CURSOR_API_KEY` (Node ≥ 22.13 через `scripts/run-with-node22.sh` / `.nvmrc`; без `node:sqlite` — fallback `JsonlLocalAgentStore`)
- MCP food-source: `FOOD_SOURCE_BASE_URL`, `FOOD_SOURCE_AUTH_TOKEN`
- Briskly: `BRISKLY_TOKEN` (CLI/MCP; для admin sync токен захватывается через CDP)
- Sidecar: `BRISKLY_SYNC_PORT` (default `8791`) — PHP смотрит на `BRISKLY_SYNC_ORCHESTRATOR_URL`
- CDP capture: `BRISKLY_CDP_URL` (default `http://127.0.0.1:9222`), `BRISKLY_CDP_CAPTURE_TIMEOUT_MS` (default `20000`), `BRISKLY_SYNC_CAPTURE_SECRET` (обязателен для `POST /capture-token`)

Поток PHP + UI и переменные service-c: [service-c README → Синхронизация Briskly](../service-c/README.md#синхронизация-briskly). PHP feature-тесты — БД `sail_db_testing`.

## CDP capture-token

Захват Bearer JWT из **уже запущенного** Chrome через `playwright-core` (`chromium.connectOverCDP`). JWT в логи не пишется (только `token_len` / `captured`).

### 1. Chrome с remote debugging

**Windows (типичный путь для WSL-хоста):**

```bat
"%ProgramFiles%\Google\Chrome\Application\chrome.exe" --remote-debugging-port=9222 --user-data-dir="%TEMP%\chrome-briskly-cdp" --no-first-run https://briskly.business/items
```

Перед запуском закрыть все окна Chrome; дальше не стартовать Chrome обычным ярлыком. Войти в Briskly в этом окне (вкладка `/items`). Проверка: `http://127.0.0.1:9222/json`. CDP endpoint: `http://127.0.0.1:9222`.

**WSL:** если Chrome запущен на Windows, порт `9222` должен быть доступен из WSL (`http://127.0.0.1:9222` или IP Windows-хоста). При необходимости пробросьте порт / используйте `BRISKLY_CDP_URL`.

**Linux Chrome:**

```bash
google-chrome --remote-debugging-port=9222 --user-data-dir=/tmp/chrome-briskly-cdp
```

### 2. Sidecar endpoint

```bash
# в .env: BRISKLY_SYNC_CAPTURE_SECRET=...  (+ при необходимости BRISKLY_CDP_URL)
npm run sidecar
```

```bash
curl -sS -X POST "http://127.0.0.1:8791/capture-token" \
  -H "X-Briskly-Capture-Secret: $BRISKLY_SYNC_CAPTURE_SECRET"
```

Ответы:

- `200` `{ "token": "<jwt>", "source": "cdp" }`
- `503` `{ "error": "capture_disabled" }` — секрет не задан в env sidecar
- `401` `{ "error": "unauthorized" }` — нет / неверный заголовок
- `503`/`422` `{ "error": "cdp_unavailable" | "no_briskly_tab" | "no_token_observed" | "timeout" }`

Контракты `GET /health` и `POST /match` без изменений.

### 3. CLI (для CLI/MCP, не для admin UI)

```bash
npm run capture-token                 # JWT в stdout
npm run capture-token -- --write-env  # записать BRISKLY_TOKEN в .env пакета
```

## Fixture match

`fixtures/match-run.json` содержит `prompt` как из PHP PromptBuilder + `llm_response`.
Прогон без Cursor: `npm run match -- --fixture fixtures/match-run.json`.
