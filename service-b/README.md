# service-b

Микросервис отчётов по объектам продаж (Sales Outlets). Обрабатывает асинхронные задачи: CSV-выгрузка и HTML-отчёт по email.

## API

Все маршруты под префиксом `/api`, middleware `trust.gateway` (заголовок `X-User-Id` от nginx-gateway).

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/data` | Проверка авторизации (debug) |
| `POST` | `/sales-outlets/reports` | Создать задачу отчёта |
| `GET` | `/sales-outlets/reports/{uuid}` | Статус задачи |
| `GET` | `/sales-outlets/reports/{uuid}/download` | Скачать CSV (только `csv_download`) |

Через gateway: префикс `/api/b`, например `POST /api/b/sales-outlets/reports`.

### Создание отчёта

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
| `csv_download` | CSV в `storage/app/exports/`, доступен через `/download` |
| `html_email` | HTML-таблица в теле письма, получатели из конфига |
| `xls_email` | *(пример ниже)* XLS во вложении, сопроводительный текст в теле, те же получатели, что у `html_email` |

Ответ `202 Accepted`:

```json
{
  "uuid": "...",
  "status": "pending",
  "report_type": "csv_download",
  "error_message": null
}
```

### Фильтры (общие для обоих типов)

| Поле | Описание |
|---|---|
| `search` | Поиск по строкам |
| `status` | `approved`, `pending`, `rejected` |
| `column_filters` | Фильтры по колонкам |
| `sort` / `direction` | Сортировка |
| `columns` | Список колонок отчёта |

## Архитектура

- **Strategy** — `CsvDownloadReportStrategy`, `HtmlEmailReportStrategy` (+ пример расширения: `XlsEmailReportStrategy`)
- **Очередь** — `BuildSalesOutletsReportJob` (worker: `service-b-queue`)
- **Shared domain** — `shared/sales-outlets-domain`
- **Таблица** — `sales_outlet_report_jobs`

### Strategy contracts (ISP)

Контракты отчётных стратегий разделены по Interface Segregation — потребители зависят только от нужных абстракций:

| Контракт | Назначение |
|---|---|
| `SalesOutletsReportProcessingStrategyInterface` | Обработка отчёта: `reportType()`, `build()`, `deliver()` — реализуют CSV и HTML |
| `SalesOutletsDownloadableReportStrategyInterface` | Marker (пустой `extends` Processing): type-level tag для downloadable-ветки; CSV-иерархия реализует marker, HTML — только Processing |
| `SalesOutletsReportStrategyResolverInterface` | `resolve(SalesOutletsReportType)` — выбор стратегии по типу отчёта |
| `SalesOutletsReportDownloadCapabilityInterface` | `supportsDownload(SalesOutletsReportType)` — проверка через `instanceof` marker в registry |

**Registry и DI:** `SalesOutletsReportStrategyRegistry` реализует resolver и capability; в `AppServiceProvider` один singleton регистрируется двумя alias на эти интерфейсы. `SalesOutletsReportJobProcessor` получает оба контракта; `SalesOutletsReportDownloadService` — только capability (не видит `resolve()`).

## Конфигурация

Единый файл `config/sales_outlets_reports.php`:

| Секция | Ключ | Назначение |
|---|---|---|
| корень | `storage_disk` | Диск для CSV-файлов |
| `types.csv_download` | `fake_delay_seconds` | Задержка в local/testing (CSV) |
| `types.html_email` | `recipients`, `subject`, `fake_delay_seconds` | Email-отчёт |

Переменные окружения: `SALES_OUTLETS_REPORTS_STORAGE_DISK`, `SALES_OUTLETS_EXPORT_FAKE_DELAY_SECONDS`, `SALES_OUTLETS_MAIL_RECIPIENTS`, `SALES_OUTLETS_MAIL_SUBJECT`, `SALES_OUTLETS_MAIL_FAKE_DELAY_SECONDS`.

## Пример: стратегия `xls_email` (XLS во вложении)

Цель: новый тип отчёта без правок `SalesOutletsReportJobProcessor` (OCP) — только enum, стратегия, DI и узкие расширения mail-слоя.

| Отличие от `html_email` | `html_email` | `xls_email` |
|---|---|---|
| Тело письма | HTML-таблица с данными | Фиксированный сопроводительный текст |
| Данные отчёта | В теле | Во вложении `.xls` |
| Получатели / тема | `MailReportConfigProviderInterface` | **Тот же** провайдер (`types.html_email`) |
| Download API | Нет | Нет (без marker `SalesOutletsDownloadableReportStrategyInterface`) |

### 1. Тип отчёта

`app/Enums/SalesOutletsReportType.php`:

```php
case XlsEmail = 'xls_email';
```

Валидация `StoreSalesOutletReportRequest` подхватит значение через `Rule::enum` автоматически.

### 2. Генерация XLS

Контракт (например `app/Contracts/SalesOutlets/XlsReportWriterInterface.php`):

```php
interface XlsReportWriterInterface
{
    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public function writeToTempFile(array $columns, iterable $rows): string;
}
```

Реализация пишет файл в `storage/app/tmp/` и возвращает абсолютный путь. `build()` стратегии возвращает этот путь как `string` (соглашение для email-стратегий с вложением).

### 3. Стратегия

`app/Services/SalesOutlets/Reports/Strategies/XlsEmailReportStrategy.php` — по образцу `HtmlEmailReportStrategy`: trait `ResolvesSalesOutletsReportData`, те же зависимости для данных и почты:

```php
class XlsEmailReportStrategy implements SalesOutletsReportProcessingStrategyInterface
{
    use ResolvesSalesOutletsReportData;

    public function __construct(
        SalesOutletsDataRepositoryInterface $dataRepository,
        SalesOutletColumnSelector $columnSelector,
        private readonly XlsReportWriterInterface $xlsWriter,
        private readonly ReportMailSenderInterface $mailSender,
        private readonly MailReportConfigProviderInterface $mailReportConfig,
    ) {
        $this->dataRepository = $dataRepository;
        $this->columnSelector = $columnSelector;
    }

    public function reportType(): SalesOutletsReportType
    {
        return SalesOutletsReportType::XlsEmail;
    }

    public function build(SalesOutletReportContextDto $context): string
    {
        $columns = $this->resolveColumns($context);

        return $this->xlsWriter->writeToTempFile(
            $columns,
            $this->resolveRows($context, $columns),
        );
    }

    public function deliver(SalesOutletAsyncJob $job, string $content): ReportDeliveryResult
    {
        $config = $this->mailReportConfig->config(); // recipients + subject из types.html_email

        $this->mailSender->sendWithXlsAttachment(
            recipients: $config->recipients,
            subject: $config->subject,
            xlsPath: $content,
            attachmentName: 'objects-sales-outlets-'.$job->uuid.'.xls',
        );

        @unlink($content);

        return ReportDeliveryResult::none();
    }
}
```

Получатели и тема — через уже существующий `ConfigMailReportConfigProvider` (`SALES_OUTLETS_MAIL_RECIPIENTS`, `SALES_OUTLETS_MAIL_SUBJECT`). Отдельная секция конфига для адресов не нужна.

Опционально — задержка в local/testing:

```php
// config/sales_outlets_reports.php
'xls_email' => [
    'fake_delay_seconds' => (int) env('SALES_OUTLETS_MAIL_FAKE_DELAY_SECONDS', 10),
],
```

### 4 Письмо: сопроводительный текст + вложение

View `resources/views/mail/sales-outlets-xls-cover.blade.php`:

```blade
<p>Здравствуйте!</p>
<p>Во вложении отчёт по объектам продаж. Параметры выборки соответствуют запросу в системе.</p>
<p>С уважением,<br>{{ config('app.name') }}</p>
```

Mailable `app/Mail/SalesOutletsXlsReportMailable.php`:

```php
public function attachments(): array
{
    return [
        Attachment::fromPath($this->xlsPath)
            ->as($this->attachmentName)
            ->withMime('application/vnd.ms-excel'),
    ];
}

public function content(): Content
{
    return new Content(view: 'mail.sales-outlets-xls-cover');
}
```

Расширение sender (`ReportMailSenderInterface` + `LaravelReportMailSender`):

```php
public function sendWithXlsAttachment(
    array $recipients,
    string $subject,
    string $xlsPath,
    string $attachmentName,
): void {
    $this->mailer->to($recipients)->send(new SalesOutletsXlsReportMailable(
        subjectLine: $subject,
        xlsPath: $xlsPath,
        attachmentName: $attachmentName,
    ));
}
```

`HtmlEmailReportStrategy` и `SalesOutletsReportMailable` при этом **не меняются**.

### 5 Регистрация в DI

`AppServiceProvider`:

```php
$this->app->bind(XlsReportWriterInterface::class, PhpSpreadsheetXlsReportWriter::class);

$this->app->tag([
    CsvDownloadReportStrategy::class,
    HtmlEmailReportStrategy::class,
    XlsEmailReportStrategy::class,
], 'sales-outlets.report-strategies');
```

Processor, registry и download-сервис трогать не нужно — registry подхватит стратегию по `reportType()`.

### 6 Запрос API

```http
POST /api/sales-outlets/reports
X-User-Id: 123
Content-Type: application/json

{
  "report_type": "xls_email",
  "search": "Курск",
  "status": "approved",
  "columns": ["id", "shop"]
}
```

Проверка в Mailhog: `docker compose up -d mailhog` → UI http://localhost:8025 — письмо с коротким текстом и вложением `.xls`.

## Тесты

```bash
# из корня репозитория
docker compose exec -T service-b php artisan test

# или через общий скрипт
./scripts/test-services.sh service-b
```

Тестовая БД: `sail_db_testing` (см. `phpunit.xml`).

## Локальный запуск

```bash
docker compose up -d service-b service-b-queue
```

Порт по умолчанию: `8082`.
