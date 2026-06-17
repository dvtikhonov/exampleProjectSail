# Yandex Maps Parser

Node.js + Playwright service for resolving Yandex Maps organization URLs and syncing reviews.

## Endpoints

| Method | Path | Body |
|--------|------|------|
| `GET` | `/health` | — |
| `POST` | `/resolve` | `{ "url": "https://yandex.ru/maps/..." }` |
| `POST` | `/sync-reviews` | `{ "org_id": "123", "canonical_url": "https://yandex.ru/maps/org/.../123/" }` |

## Environment

| Variable | Default | Description |
|----------|---------|-------------|
| `PORT` | `3000` | HTTP port |
| `MOUSE_JIGGLE_MIN_PX` | `10` | Minimum mouse jiggle distance |
| `MOUSE_JIGGLE_MAX_PX` | `80` | Maximum mouse jiggle distance |
| `HEADLESS` | `true` | Run Chromium headless |
| `RESOLVE_CANDIDATE_LIMIT` | `30` | Max search candidates |
| `SYNC_MAX_IDLE_ITERATIONS` | `15` | Stop scroll after N idle iterations |
| `SYNC_SCROLL_DELAY_MS` | `800` | Delay between scroll steps |
| `NAVIGATION_TIMEOUT_MS` | `60000` | Page navigation timeout |

## Development

```bash
cd yandex-parser
npm install
npm run dev
npm test
```

## Docker

Included in root `docker-compose.yml` as `yandex-parser` (internal port 3000).

```bash
docker compose up -d yandex-parser
curl http://localhost:3000/health
```

## Notes

- `humanMouseJiggle` runs before every page action to reduce bot detection risk.
- Parsing uses network JSON interception with DOM fallback.
- Yandex Maps DOM/API may change; monitor sync failures in production.
