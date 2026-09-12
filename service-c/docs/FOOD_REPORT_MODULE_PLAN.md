# План: модуль отчётов Food (`FoodReport`)

Статус: **реализовано** (варианты B, Phases 0–5; `migrate` / backfill на shared/prod — только по отдельному согласию)  
Сервис: `service-c`  
Дата плана: 2026-09-10  
Документация runtime: [service-c/README.md — Отчёты Food](../README.md#отчёты-food-foodreport)

## Зафиксированные решения

| # | Вопрос | Решение |
|---|--------|---------|
| 1 | Миграция `max_food_order_items` | **файл собрать**; `migrate` на shared/prod — только по отдельному согласию |
| 2 | Ось даты по умолчанию | **`delivery_date`** (fallback `DATE(created_at)` если null) |
| 3 | База выручки и среднего чека | **`items_total`** (без доставки) |
| 4 | Формат файла | **`.xlsx`** |
| 5 | Состав заказов в отчёте | **только выполненные** = `OrderStatus::Confirmed` |
| 6 | UI | отправка `.xlsx` в чат MAX менеджеру (без превью на экране) |
| 7 | Выбор отчёта | `revenue` \| `top_dishes` (без combined) |
| 8 | Архитектура данных | **Вариант B** (таблица строк + sync) |
| 9 | Запись в `max_food_order_items` | **только** при статусе «Выполнен» (`confirmed`) |

**Следствие:** runtime — **вариант B**. Sync пишет items **только** для `confirmed`; иначе удаляет строки по `order_id`. Query всё равно фильтрует confirmed. Файл миграции в репо; `migrate` на shared/prod без согласия не выполнять. Тесты (`RefreshDatabase`) применяют схему сами.

## Цель

Добавить **явный внутренний модуль** отчётности:

1. **Выручка за период** — по дням: дата, кол-во заказов, средний чек, сумма (`items_total`).
2. **Топ позиций** — по дням: наименование, количество, сумма (`line_total` из `max_food_order_items`).
3. **Вариант B** — строки items только для выполненных заказов; sync при create/`confirmed`/composition.
4. Выгрузка в `.xlsx` (PhpSpreadsheet) и **отправка файла в чат MAX** менеджеру.
5. **Форма в разделе «Заказы»** (роль `max_manager`) — период + ресторан + выбор отчёта.

Модульность: feature-модуль внутри монолита `service-c`.  
CQRS-lite: write синхронизирует items только для confirmed; query/export читают orders + items.
---

## Контекст проекта (исходные ограничения)

| Факт | Следствие |
|------|-----------|
| Отдельного report API нет | Новый модуль с нуля |
| Позиции в JSON `items_snapshot` у `max_food_orders` | Источник для sync в `max_food_order_items`; snapshot остаётся для UI/чата |
| `app/Modules/` отсутствует | Создать `app/Modules/FoodReport/` |
| `phpoffice/phpspreadsheet` уже в `composer.json` | Writer без новой зависимости |
| У `max_manager` ресторан **не глобальный** (только в flow оформления) | В форме отчёта — явный select ресторана |
| Страница менеджера / отчёты | Раздел «Заказы» (`OrdersAdminRoot` / `AdminHomePage`), middleware `food.order.admin:max_manager` на API |
| Экспорта заказов нет (есть только импорт меню XLS) | Export строить с нуля |
| Миграции | Только после явного согласия |
| Тестовая БД | `sail_db_testing` |
| `MAX_REPORT_*` | Чаты уведомлений меню / «тест бот» — **не** путать с аналитикой |

### Полезные точки кода

- Создание snapshot: `OrderItemsSnapshotBuilder`, `OrderFromCartCreator`
- Submit: `CustomerOrderSubmissionService`, `ManualOrderSubmissionService`
- Правка состава: `OrderCompositionUpdateService`, `OrderCompositionSnapshotBuilder`
- Период в manual list: `ListManualOrdersRequest`, `EloquentFoodOrderAdminReadRepository::manualOrdersQuery`
- Сумма: `sumManualOrdersTotal`
- DI: `FoodServiceProvider` → образец для `FoodReportServiceProvider`
- API prefix: `/api/food/admin/...`
- Роль: `FoodOrderAdminRole::MaxManager`, middleware `food.order.admin:max_manager`
- UI роль: `useAuth` → `hasMaxManagerRole`, форма отчётов в секции `orders` (`AdminHomePage`)

---

## Архитектура модуля

### Расположение (явный внутренний модуль)

```
service-c/app/Modules/FoodReport/
├── Contracts/
│   ├── FoodReportQueryServiceInterface.php
│   ├── FoodOrderItemWriteRepositoryInterface.php
│   ├── FoodOrderReportRepositoryInterface.php
│   ├── FoodReportSpreadsheetExporterInterface.php
│   └── FoodReportMaxDeliveryInterface.php
├── Services/
│   ├── FoodReportQueryService.php
│   ├── FoodOrderItemSyncService.php
│   ├── PhpSpreadsheetFoodReportExporter.php
│   └── FoodReportMaxDeliveryService.php
├── Repositories/
│   ├── EloquentFoodOrderItemWriteRepository.php
│   └── EloquentFoodOrderReportRepository.php
├── DTO/
│   ├── RevenueDayRowDto.php
│   ├── RevenueReportDto.php
│   ├── TopDishDayRowDto.php
│   ├── TopDishesReportDto.php
│   └── ReportFilterDto.php
├── Enums/
│   ├── ReportDateAxis.php                   # DeliveryDate | CreatedAt
│   └── ReportType.php                       # Revenue | TopDishes
├── Models/
│   └── FoodOrderItem.php
├── Http/
│   ├── Controllers/
│   │   ├── AdminFoodReportQueryController.php
│   │   └── AdminFoodReportExportController.php
│   └── Requests/
│       ├── RevenueReportRequest.php
│       ├── TopDishesReportRequest.php
│       └── ExportFoodReportRequest.php
└── Providers/
    └── FoodReportServiceProvider.php
```### Изоляция и SOLID

- В `Contracts/` и `Services/` модуля — **запрещены** `App\Models\*` и Laravel Facades (`DB::`, `Log::` и т.п.).
- Eloquent — только в `Repositories/` (+ Model модуля).
- HTTP: FormRequest → Controller → Service Interface → Repository Interface.
- Регистрация: `FoodReportServiceProvider` в `bootstrap/providers.php`.
- CI: расширить isolation-check путями модуля **или** отдельный `scripts/check-food-report-module-isolation.sh` + Architecture test.
- **SRP:** не раздувать `ManualOrderQueryService` / `AdminOrderQueryService`.
- **DIP:** сервисы зависят от контрактов модуля.
- **OCP:** смена B→C (витрины) — только реализация репозитория, HTTP-контракт стабилен.
- **ISP:** отдельные методы/интерфейсы query vs write vs export.

### Соответствие вариантам из обсуждения

| Вариант | В плане |
|---------|---------|
| A — онлайн по JSON | Не использовать в runtime |
| **B — `order_items`** | **Основной runtime** |
| C — суточные витрины | Опционально позже, поверх B |
| D — гибрид | Не нужен: сразу B |

---

## Доменные правила (зафиксировано)

| Параметр | Решение | Примечание |
|----------|---------|------------|
| Статус в отчёте | только **выполненные**: `OrderStatus::Confirmed` | pending / draft / rejected не входят |
| Ось даты | `delivery_date` («Блюда на»), fallback `DATE(created_at)` если null | Enum `ReportDateAxis` + query-param |
| Выручка / средний чек | `SUM(items_total)`, avg = `SUM(items_total) / COUNT(orders)` по confirmed | **Без** доставки |
| Топ блюд | строки только из confirmed-заказов (sync не пишет иное) | Query дополнительно фильтрует confirmed |
| `restaurant_id` | обязателен в API и UI (если не выбран в flow — показать select) | |
| Manual vs client | все confirmed ресторана | Не только `is_manual` |
### Формы строк отчётов

**Выручка (день):**

- `date` — Y-m-d
- `orders_count` — int
- `average_check` — money string
- `amount` — money string

**Топ позиций (день → позиции):**

- `dish_id` (nullable)
- `dish_name`
- `quantity`
- `amount` (сумма `line_total`)

**Meta:** итоги за период (orders_count, average_check, amount).

---

## Данные: вариант A (текущий этап)

## Данные: вариант B (runtime)

### Выручка

В `EloquentFoodOrderReportRepository`:

- фильтр: `status = confirmed`, `restaurant_id`, период по оси `COALESCE(delivery_date, DATE(created_at))`;
- `GROUP BY` даты по `max_food_orders`;
- `COUNT(*)` → `orders_count`;
- `SUM(items_total)` → `amount`;
- средний чек = `amount / orders_count`.

### Топ позиций

- SQL по `max_food_order_items` (в таблице уже только confirmed) + join/where `status = confirmed` как защита;
- агрегация `(report_date, dish_id)` по `quantity` и `line_total`;
- `limit` на день (default 20).

`items_snapshot` остаётся для UI/чата и как источник sync.

### Sync (write-path) — только «Выполнен»

`FoodOrderItemSyncService::syncIfConfirmed(FoodOrderRecord $order)`:

- если `status === Confirmed` → replace rows by `order_id` из `items_snapshot` + `report_date` + `restaurant_id`;
- иначе → `deleteByOrderId(order_id)` (строк для невыполненных быть не должно).

**Хуки:**

1. `OrderFromCartCreator` — после create, только если статус сразу `confirmed` (ручные / draft-after-scanning).
2. `OrderReviewStepHandler` (рядом с `notifyIfFullyApproved`) — при первом переходе в `confirmed`.
3. `OrderCompositionUpdateService` — после update snapshot: sync если confirmed, иначе delete.

**Backfill:** только заказы `status = confirmed`.

Query по-прежнему фильтрует confirmed (защита на чтении).

### Миграция и backfill

- Файл миграции — Phase 0b; **не** `migrate` на shared/prod без согласия.
- Команда `food-report:backfill-order-items` — только confirmed; запуск на прод — по согласию.
- Тесты: `RefreshDatabase` на `sail_db_testing`.
---

## Схема `max_food_order_items`

### Таблица `max_food_order_items` (схема файла миграции)

| Колонка | Тип | Назначение |
|---------|-----|------------|
| `id` | bigint PK | |
| `order_id` | FK → `max_food_orders`, cascade delete | |
| `restaurant_id` | index | фильтр без лишнего join |
| `report_date` | date, index | ось отчёта |
| `dish_id` | nullable unsignedBigInteger | |
| `dish_name` | string | снимок имени |
| `unit_price` | decimal(10,2) | |
| `quantity` | unsignedInteger | |
| `line_total` | decimal(10,2) | |
| `created_at` / `updated_at` | timestamps | |

Индексы: `(restaurant_id, report_date)`, `(order_id)`, `(report_date, dish_id)`.

Sync: `FoodOrderItemSyncService` из хуков `OrderFromCartCreator` + `OrderCompositionUpdateService`.  
Backfill: `food-report:backfill-order-items`.  
Выручка по-прежнему из `max_food_orders.items_total`; топ — из `max_food_order_items`.
---

## API (`max_manager`)

Префикс: `/api/food/admin/reports`  
Middleware: `max.miniapp.auth` + `food.order.admin:max_manager`

| Method | Path | Назначение |
|--------|------|------------|
| `GET` | `/revenue` | JSON: дни + meta итогов |
| `GET` | `/top-dishes` | JSON: по дням позиции (опц. `limit`) |
| `GET` | `/export` | ~~binary download~~ → `POST /export` — .xlsx в чат MAX |

### Query / FormRequest

- `date_from`, `date_to` — `Y-m-d`, required, `to >= from`, max span (например 93 дня)
- `restaurant_id` — required, exists
- `date_axis` — optional, default `delivery_date`
- `report_type` (export и UI) — обязательный: `revenue` \| `top_dishes`
- `limit` (top) — optional, default 20 на день

Деньги через существующий `FoodMoneyFormatterInterface` (из Food Shared).

### Excel (PhpSpreadsheet)

- Состав: один лист по `report_type` — «Выручка» или «Топ позиций»
- Лист «Выручка»: Дата (asc) \| Кол-во \| Средний чек \| Сумма (+ Итого)
- Лист «Топ позиций»: перекрёстная таблица — «Наименование блюд» + даты (asc), на каждую дату подколонки Кол-во \| Сумма
- Имя файла: `report_{restaurantId}_{from}_{to}.xlsx`
- Writer: PhpSpreadsheet Xlsx
- Доставка: `FoodReportMaxDeliveryInterface` → Bot API `POST /uploads?type=file` + `POST /messages?user_id=…` с `attachments.type=file`
- Ответ API: JSON `{ ok, filename, message }` (не browser download)
- Контракт `FoodReportSpreadsheetExporterInterface`; реализация без протекания в query-service деталей Writer

Пример JSON выручки:

```json
{
  "days": [
    { "date": "2026-09-01", "orders_count": 12, "average_check": "850.00", "amount": "10200.00" }
  ],
  "meta": { "orders_count": 40, "average_check": "900.00", "amount": "36000.00" }
}
```

Пример JSON топа:

```json
{
  "days": [
    {
      "date": "2026-09-01",
      "items": [
        { "dish_id": 5, "dish_name": "Борщ", "quantity": 18, "amount": "5400.00" }
      ]
    }
  ]
}
```

---

## UI — форма в разделе «Заказы» (`max_manager`)

**Где:** `FoodReportForm` на `AdminHomePage` внутри `OrdersAdminRoot` (вкладка «Заказы»), только при `hasMaxManagerRole`. Вкладка «Заказы» доступна и при роли `max_manager` (без review — только форма отчётов).

**Не** вшивать в «Ручные заказы» (`ManualOrderUserSelectPage`).

### Поля формы

1. Период: `date_from` / `date_to` (можно согласовать UX с фильтрами списка).
2. Ресторан: select — обязательный выбор из списка ресторанов (`fetchRestaurants` / `useRestaurantsMenu`).
3. **Выбор отчёта** (`report_type`, обязательный select):
   - «Выручка за период» → `revenue`
   - «Топ позиций» → `top_dishes`
4. Действие: **«Отправить в MAX»** → `.xlsx` через `POST /export` в диалог менеджера (без превью таблиц на экране).

JSON `/revenue` и `/top-dishes` — для тестов и возможного будущего UI; форма менеджера их не вызывает.

### Файлы фронта (ориентир)

- `resources/js/max-app/api/admin/reports.js`
- `resources/js/max-app/composables/useFoodReport.js`
- `resources/js/max-app/components/admin/FoodReportForm.vue` (Tailwind, стиль admin)
- Встройка в `AdminHomePage.vue` / `OrdersAdminRoot.vue`

Скачивание: axios `responseType: 'blob'` + `URL.createObjectURL`.

---

## Связь с существующим кодом (минимальные касания)

| Место | Изменение (вариант B) |
|-------|----------------------|
| `bootstrap/providers.php` | `FoodReportServiceProvider` |
| `routes/api.php` | группа `food/admin/reports` |
| `OrdersAdminRoot` / `AdminHomePage` | форма отчётов → только download |
| Isolation scripts / Architecture tests | пути модуля |
| `OrderFromCartCreator` | sync **если** статус сразу `confirmed` |
| `OrderReviewStepHandler` | sync при переходе в `confirmed` |
| `OrderCompositionUpdateService` | sync если confirmed, иначе delete items |
| `database/migrations/` | файл `max_food_order_items`; **не** `migrate` на shared/prod без согласия |

---

## Этапы реализации

### Phase 0 — каркас модуля

- [x] Каталоги `app/Modules/FoodReport/` (включая Model/Sync/Write contracts)
- [x] Provider + контракты
- [x] Регистрация в `bootstrap/providers.php`

### Phase 0b — файлы миграции (без migrate на прод)

- [x] Файл миграции `create_max_food_order_items_table` в `service-c/database/migrations/`
- [x] **Не** запускать `artisan migrate` / деплой `run_migrations` без отдельного согласия
- [x] В README: пометка «миграция в репо; применение — по согласию»

### Phase 1 — Variant B write-path (только выполненные)

- [x] Model `FoodOrderItem` + write repository (`replace` / `deleteByOrderId`)
- [x] `FoodOrderItemSyncService::syncIfConfirmed` — писать только при `confirmed`, иначе delete
- [x] Хук `OrderFromCartCreator` (create с `confirmed`)
- [x] Хук `OrderReviewStepHandler` (переход в `confirmed`)
- [x] Хук `OrderCompositionUpdateService` (update при `confirmed` / delete иначе)
- [x] Команда `food-report:backfill-order-items` — только confirmed (запуск на прод — по согласию)
- [x] Unit-тесты: pending → пусто; confirmed → строки; composition; rejected → пусто

### Phase 2 — Query API (чтение B)

- [x] Report repository: выручка по `items_total` + топ из `max_food_order_items`
- [x] Query service + DTO
- [x] Controllers + FormRequests
- [x] Routes + middleware `max_manager`
- [x] Feature-тесты на `sail_db_testing`

### Phase 3 — Excel export (`.xlsx`)

- [x] Exporter на PhpSpreadsheet (Xlsx)
- [x] `GET .../export` + `report_type`
- [x] Тест: заголовки, лист, содержимое

### Phase 4 — UI `max_manager`

- [x] Форма в разделе «Заказы» (`AdminHomePage`), только `max_manager`
- [x] Только скачивание `.xlsx` (без превью)
- [x] Выбор ресторана в форме (обязательный select)

### Phase 5 — документация

- [x] Фрагмент в `service-c/README.md` (B, эндпоинты, migrate/backfill по согласию)
- [x] Isolation CI: `scripts/check-food-report-module-isolation.sh` + `tests/Architecture/FoodReportModuleIsolationTest`

### Отдельно (не в этой итерации без согласия)

- [ ] `migrate` на shared/prod
- [ ] Запуск backfill на рабочих данных

---

## Тесты

- `tests/Feature/FoodReport/` — API; rejected/pending/draft не влияют на выручку и топ; только confirmed
- `tests/Unit/Modules/FoodReport/` — sync только для confirmed; exporter `.xlsx`
- БД: **`sail_db_testing`**
- Изоляция модуля — скрипт и/или Architecture test

---

## Риски и решения

| Риск | Решение |
|------|---------|
| Расхождение snapshot и items | Sync только на confirmed + backfill confirmed; delete если не confirmed |
| Таблица ещё не применена на прод | Файл в репо; README; отчёты на проде после migrate+backfill |
| `delivery_date` null | Fallback `DATE(created_at)`; подпись оси в UI |
| Тяжёлый export большого периода | Лимит дней в FormRequest |
| Путаница «Заказы» review vs отчёты | Форма только при `hasMaxManagerRole`; очередь — при review-ролях |
| Путаница `total` vs `items_total` | В коде и README: выручка = `items_total` |

---

## Порядок старта

1. ~~Закрыть открытые решения~~ — **сделано** (в т.ч. **вариант B**).
2. ~~Phase 0 → 0b → 1 → 2 → 3 → 4 → 5 (docs)~~ — **сделано**.
3. `migrate` / backfill на shared/prod — только после отдельного согласия.

Этот файл — источник правды для решений и чеклистов; runtime-описание — в [README](../README.md#отчёты-food-foodreport). При изменении решений обновлять таблицы выше.
