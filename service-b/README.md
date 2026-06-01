# service-b

Микросервис асинхронной генерации отчётов по объектам продаж (Sales Outlets). Поддерживает два типа отчётов:

- `csv_download` — формирует CSV-файл для скачивания;
- `html_email` — отправляет отчёт HTML-таблицей на email.

## API

Все маршруты доступны под префиксом `/api` и защищены middleware `trust.gateway` (ожидается `X-User-Id` от `nginx-gateway`).

Через gateway используется префикс `/api/b`, например: `POST /api/b/sales-outlets/reports`.

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/data` | Проверка gateway-авторизации (debug) |
| `GET` | `/sales-outlets/reports/stats` | Агрегированная статистика задач по типам отчётов |
| `POST` | `/sales-outlets/reports` | Создать асинхронную задачу отчёта |
| `GET` | `/sales-outlets/reports/{uuid}` | Получить статус задачи |
| `GET` | `/sales-outlets/reports/{uuid}/download` | Скачать файл (только для `csv_download`) |

## Создание отчёта

```http
POST /api/sales-outlets/reports
X-User-Id: 123
Content-Type: application/json

{
  "report_type": "csv_download",
  "search": "Курск",
  "status": "approved",
  "column_filters": { "shop": "Курск" },
  "sort": "shop",
  "direction": "desc",
  "columns": ["id", "shop"]
}
```

`report_type`:

| Значение | Поведение |
|---|---|
| `csv_download` | Сохраняет CSV в файловом хранилище и делает доступным через `/download` |
| `html_email` | Отправляет HTML-таблицу по email получателям из конфигурации |

Ответ `202 Accepted`:

```json
{
  "uuid": "8d6e6f8c-2f5c-4eb9-9f46-f3f3fe28c500",
  "status": "pending",
  "report_type": "csv_download",
  "error_message": null
}
```

Возможные статусы задачи: `pending`, `processing`, `completed`, `failed`.

### Фильтры

| Поле | Описание |
|---|---|
| `search` | Поиск по строковым полям |
| `status` | Статус объекта (`approved`, `pending`, `rejected`) |
| `column_filters` | Фильтры по конкретным колонкам |
| `sort` / `direction` | Сортировка (`asc` / `desc`) |
| `columns` | Явный список колонок для отчёта |

## Статистика задач

Запрос:

```http
GET /api/sales-outlets/reports/stats
X-User-Id: 123
```

Ответ:

```json
{
  "by_type": {
    "csv_download": {
      "pending": 1,
      "processing": 0,
      "completed": 10,
      "failed": 2,
      "total": 13
    },
    "html_email": {
      "pending": 0,
      "processing": 1,
      "completed": 7,
      "failed": 0,
      "total": 8
    }
  },
  "generated_at": "2026-06-01T09:10:15+00:00"
}
```

## Архитектура

- Strategy-обработчики: `CsvDownloadReportStrategy`, `HtmlEmailReportStrategy`;
- Очередь: `BuildSalesOutletsReportJob` (воркер `service-b-queue`);
- Shared domain: `shared/sales-outlets-domain`;
- Хранилище задач: таблица `sales_outlet_report_jobs`.

Ключевые контракты:

- `SalesOutletsReportProcessingStrategyInterface` — единый контракт обработки отчёта;
- `SalesOutletsDownloadableReportStrategyInterface` — marker-интерфейс для downloadable-стратегий;
- `SalesOutletsReportStrategyResolverInterface` — выбор стратегии по `report_type`;
- `SalesOutletsReportDownloadCapabilityInterface` — проверка возможности скачивания.

`SalesOutletsReportStrategyRegistry` зарегистрирован как singleton и алиасится на узкие интерфейсы (resolver/capability/presentation), чтобы потребители зависели только от нужных абстракций.

## Конфигурация

Основной файл: `config/sales_outlets_reports.php`.

| Секция | Ключ | Назначение |
|---|---|---|
| корень | `storage_disk` | Диск хранения файлов отчётов |
| `types.csv_download` | `fake_delay_seconds` | Искусственная задержка в `local/testing` |
| `types.html_email` | `recipients`, `subject`, `fake_delay_seconds` | Email-получатели, тема и задержка |

Переменные окружения:

- `SALES_OUTLETS_REPORTS_STORAGE_DISK`
- `SALES_OUTLETS_EXPORT_STORAGE_DISK` (legacy fallback)
- `SALES_OUTLETS_EXPORT_FAKE_DELAY_SECONDS`
- `SALES_OUTLETS_MAIL_RECIPIENTS`
- `SALES_OUTLETS_MAIL_SUBJECT`
- `SALES_OUTLETS_MAIL_FAKE_DELAY_SECONDS`

Для `html_email` в локальной среде используется `mailhog` (`http://localhost:8025`).

## Локальный запуск

Из корня репозитория:

```bash
docker compose up -d service-b service-b-queue mailhog
```

Сервис доступен на порту `8082` (по умолчанию, можно переопределить `SERVICE_B_PORT`).

## Тесты

```bash
# из корня репозитория
docker compose exec -T service-b php artisan test

# или через общий скрипт
./scripts/test-services.sh service-b
```

Тестовая БД: `sail_db_testing` (см. `service-b/phpunit.xml` и `service-b/.env.testing`).
