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
```

## Env

См. `.env.example`. Секреты не коммитить.

- Live match: `CURSOR_API_KEY` (для `@cursor/sdk` желателен Node ≥ 22.13)
- MCP food-source: `FOOD_SOURCE_BASE_URL`, `FOOD_SOURCE_AUTH_TOKEN`
- Briskly: `BRISKLY_TOKEN`
- Sidecar: `BRISKLY_SYNC_PORT` (default `8791`) — PHP смотрит на `BRISKLY_SYNC_ORCHESTRATOR_URL`

Поток PHP + UI и переменные service-c: [service-c README → Синхронизация Briskly](../service-c/README.md#синхронизация-briskly). PHP feature-тесты — БД `sail_db_testing`.

## Fixture match

`fixtures/match-run.json` содержит `prompt` как из PHP PromptBuilder + `llm_response`.
Прогон без Cursor: `npm run match -- --fixture fixtures/match-run.json`.
