# service-i — MStroy Frontend

Frontend-only SPA (Vue 3 + TypeScript + AgGrid Enterprise tree) по ТЗ MStroy_Frontend v3.2.

## Запуск

### Docker (канон)

```bash
docker compose up -d --build service-i
```

UI: [http://localhost:8089](http://localhost:8089)  
Через gateway: [http://mstroy.localhost:8080](http://mstroy.localhost:8080) (нужна запись `mstroy.localhost` в `/etc/hosts`).

Runtime-образ — `nginx:alpine` (без Node / Nuxt).

### Dev на хосте

```bash
cd service-i
npm ci
npm run dev
```

## Скрипты

| Команда | Назначение |
|---|---|
| `npm run dev` | Vite dev-server |
| `npm run build` | `vue-tsc -b` + production build |
| `npm run preview` | preview собранного `dist` |
| `npm test` | unit / Vue / perf через Vitest |
| `npm run test:perf` | только perf-тесты TreeStore |

## TreeStore

Модуль без импортов Vue — для внешних автотестов:

```ts
import { TreeStore } from './src/tree'
```

- `getAll()` возвращает shallow copy внутреннего массива.
- **Порядок элементов в `getAllChildren` не гарантируется** (проверяйте состав id, не BFS/DFS-порядок).

### Perf-ориентиры (`npm run test:perf`)

На дереве N ≈ 50 000 (глубина ~10), локально (сервис вне CI монорепо):

| Операция | Порог |
|---|---|
| `getItem` | &lt; 1 ms |
| `getChildren` (корень) | &lt; 5 ms |
| `getAllParents` (лист) | &lt; 5 ms |
| `setItems` | &lt; 500 ms |
| `removeItem` (поддерево) | &lt; 100 ms |

## Структура

```
service-i/
  public/items.json   # статика для fetch('/items.json')
  src/tree/           # TreeStore + types
  src/components/     # TreeGrid (AgGrid)
  Dockerfile          # multi-stage: node build → nginx runtime
```
