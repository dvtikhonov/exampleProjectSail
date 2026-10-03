# Разведка Briskly company API (часть 2)

Источник: бандл кабинета `https://briskly.business/main-*.js` (сент. 2026) +
`/mnt/c/projects/briskly/changePrices.php`.

## Базы

| Клиент в JS | Base URL |
|-------------|----------|
| `company` (v1) | `https://briskly.business/api/company/v1` |
| `companyV2` | `https://briskly.business/api/company/v2` |

В нашем клиенте `baseUrl` = `https://briskly.business/api/company`, пути с `/v1/...` и `/v2/...`.

## Endpoints

| Операция | Method | Path | API version |
|----------|--------|------|-------------|
| list items | GET | `/dashboard/item/get-list` | v2 |
| get by id | GET | `/dashboard/item/get-by-id` | v1 |
| update item | POST | `/dashboard/item/update` | v2 |
| create item | POST | `/dashboard/item/create` | **v1** |
| list categories | GET | `/dashboard/category/get-list` | v2 |

## Фильтры поиска (контракт синка)

- **Не использовать** `filters[category_id]` при snapshot/list.
- Поиск по тексту в кабинете для меню — **client-side** (`filterItemsString` по `name`/`id`).
  Наш клиент фильтрует `searchText` после fetch по подстроке имени.
- Дефолтные фильтры списка (как в PHP, без категории): `parent_id=0`, `!catalog.item_type=coffee`.

## CREATE payload

`createItem(params)` в JS прокидывает объект as-is. Поля взяты из рабочего
`update` payload (`changePrices.php`) **без `id`**: name, catalog_id, category_id,
price, barcode/barcodes, cost, vat_*, unit_*, status, heating_*, sticker_enabled,
article, props, …

Для синка достаточно: `name` + `price` (из VPS) + `category_id` (выбор в UI) + `catalog_id`
(из выбранной категории / контекста каталога).

## Auth

`Authorization: Bearer <JWT>`. Токен короткоживущий; не коммитить, не логировать.
