# service-b

Микросервис асинхронной генерации отчётов по объектам продаж (Sales Outlets). Поддерживает типы отчётов:

| `report_type` | Доставка |
|---|---|
| `csv_download` | CSV в файловом хранилище → `GET .../download` |
| `html_email` | HTML-таблица по SMTP (Mailhog в dev) |
| `max_message` | Intro-текст + **CSV-вложение** в MAX Bot API ([документация MAX](https://dev.max.ru/docs-api)): `POST /uploads` → `POST /messages` с `attachments` |

**Связанные документы**

| Документ | Назначение |
|---|---|
| [корневой README](../README.md) | Docker, gateway, Reverb, E2E, диагностика live-stats |
| [service-b-reports-strategy](../.cursor/rules/service-b-reports-strategy.mdc) | Зафиксированные решения Strategy (не менять без запроса) |
| [docs/plans/max-messenger-sales-outlets.md](../docs/plans/max-messenger-sales-outlets.md) | План и статус интеграции MAX |
| [REFACTORING-CHECKLIST.md](./REFACTORING-CHECKLIST.md) | Чеклист рефакторинга Reports (фазы 0–8) |
| [ORPHAN-FILES.md](./ORPHAN-FILES.md) | Аудит legacy/orphan (актуально: orphan в `app/` нет) |

## Структура (ключевые каталоги)

```
service-b/
├── app/
│   ├── Contracts/Max/              # MaxMessengerClient, ReportMaxMessageSender
│   ├── Contracts/SalesOutlets/     # Strategy, orchestrator, download, lifecycle
│   ├── Domain/SalesOutlets/        # SalesOutletAsyncJob
│   ├── Events/                     # SalesOutletReportJobMutated, ReportJobStatsChanged
│   ├── Http/Controllers/Api/       # SalesOutletsReport*, Stats
│   ├── Jobs/BuildSalesOutletsReportJob.php
│   ├── Listeners/                  # broadcast stats, audit log
│   ├── Services/Max/               # HttpMaxMessengerClient, LaravelReportMaxMessageSender
│   └── Services/SalesOutlets/Reports/Strategies/
│       ├── CsvDownloadReportStrategy.php
│       ├── HtmlEmailReportStrategy.php
│       └── MaxMessageReportStrategy.php   # extends AbstractSalesOutletsCsvReportStrategy
├── config/sales_outlets_reports.php
├── database/migrations/            # sales_outlet_report_jobs
├── routes/api.php
└── tests/                          # Feature + Unit (БД: sail_db_testing)
```

Shared-пакет: `../shared/sales-outlets-domain` (CSV writer, query filters, `AbstractStrategyReport`).

## API

Все маршруты доступны под префиксом `/api` и защищены middleware `trust.gateway` (ожидается `X-User-Id` от `nginx-gateway`).

Через gateway используется префикс `/api/b`, например: `POST /api/b/sales-outlets/reports`.

| Метод | Путь | Контроллер | Описание |
|---|---|---|---|
| `GET` | `/data` | closure | Проверка gateway-авторизации (только `local` / `testing`) |
| `GET` | `/sales-outlets/reports/stats` | `SalesOutletsReportStatsController` | Агрегированная статистика задач по типам |
| `POST` | `/sales-outlets/reports` | `SalesOutletsReportController` | Создать асинхронную задачу (`202 Accepted`) |
| `GET` | `/sales-outlets/reports/{uuid}` | `SalesOutletsReportController` | Статус задачи |
| `GET` | `/sales-outlets/reports/{uuid}/download` | `SalesOutletsReportController` | Скачать CSV (`csv_download` только) |

`GET .../download`: `404` — задача не найдена или тип без download; `409` — файл ещё не готов (`status` не `completed`); `200` — streamed-ответ.

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
| `max_message` | Отправляет intro-текст и CSV-файл в чаты/диалоги MAX (`POST /uploads` + `POST /messages`) |

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
    },
    "max_message": {
      "pending": 0,
      "processing": 0,
      "completed": 0,
      "failed": 0,
      "total": 0
    }
  },
  "generated_at": "2026-06-01T09:10:15+00:00"
}
```

### Live-статистика: термины, broadcast и фронтенд

| Термин | Значение |
|---|---|
| **snapshot** | Начальный REST-снимок статистики (`by_type`, `generated_at`) |
| **broadcast** | Reverb после мутации: `SalesOutletReportJobMutated` → listener → `ReportJobStatsChanged` |
| **channel** | Private-канал `report-jobs.stats` (`Echo.private('report-jobs.stats')`) |
| **event** | Broadcast-событие `ReportJobStatsChanged` (в Echo: `.ReportJobStatsChanged`) |
| **payload** | JSON с полями `by_type` и `generated_at` — одинаковый формат для snapshot и event |

Помимо REST (`GET /api/sales-outlets/reports/stats`), сервис публикует изменение агрегатов через broadcaster:

- `EloquentSalesOutletsReportJobRepository` после `create()` / `updateStatus()` диспатчит domain event `SalesOutletReportJobMutated`;
- listeners на `SalesOutletReportJobMutated`: `BroadcastReportJobStatsOnJobMutation` (live stats), `LogSalesOutletReportJobMutation` (audit log);
- broadcaster диспатчит **event** `ReportJobStatsChanged` через `EventDispatcherInterface` с **payload** из `SalesOutletsReportStatsRepositoryInterface::aggregate()`;
- событие публикуется в **channel** `PrivateChannel('report-jobs.stats')`.

Реализация в `service-b`:

- `app/Events/SalesOutletReportJobMutated.php`
- `app/Listeners/BroadcastReportJobStatsOnJobMutation.php`
- `app/Listeners/LogSalesOutletReportJobMutation.php`
- `app/Repositories/SalesOutlets/EloquentSalesOutletsReportJobRepository.php`
- `app/Repositories/SalesOutlets/EloquentSalesOutletsReportStatsRepository.php`
- `app/Services/SalesOutlets/SalesOutletsReportStatsBroadcaster.php`
- `app/Events/ReportJobStatsChanged.php`

**Как статистика доходит до фронтенда** (через `main-app`):

1. Загружается **snapshot**: `GET /objects-sales-outlets-2/reports/stats` → прокси в `/api/sales-outlets/reports/stats`.
2. Подписка на **channel** `report-jobs.stats` через Laravel Echo/Reverb; авторизация — `POST /broadcasting/auth`.
3. При `create` / `updateStatus` `service-b` отправляет **broadcast** **event** `ReportJobStatsChanged`.
4. `useReportJobStats` (`resources/js/Composables/useReportJobStats.js`) применяет **payload** к UI без перезагрузки страницы.

Файлы `main-app`: `resources/js/bootstrap.js`, `app/Http/Controllers/ObjectsSalesOutletsController.php`.

Диагностика live-статистики — в [корневом README.md](../README.md) (раздел **Диагностика live-статистики**).

### Domain events и listeners

Live-stats обновляются через domain event: persistence не вызывает broadcast напрямую, а диспатчит событие; реакции подключаются listener'ами. Цепочка из трёх уровней:

```
EloquentSalesOutletsReportJobRepository
    → SalesOutletReportJobMutated          (domain event: «job изменился»)
        → BroadcastReportJobStatsOnJobMutation   → ReportJobStatsChanged → Reverb
        → LogSalesOutletReportJobMutation          → PSR-3 log (audit)
```

| Уровень | Класс | Ответственность |
|---|---|---|
| Persistence | `EloquentSalesOutletsReportJobRepository` | Запись в БД + dispatch `SalesOutletReportJobMutated` |
| Реакция | `app/Listeners/*` | Побочные эффекты **без** правки репозитория и processor |
| Transport | `ReportJobStatsChanged` | Готовый snapshot для WebSocket (Echo/Reverb) |

**Почему так (SOLID):**

- **SRP** — репозиторий только сохраняет; broadcast и лог — отдельные listener'ы.
- **OCP** — новая реакция на мутацию = новый listener + `Event::listen`, без изменения API-сервиса, orchestrator, strategies и worker.
- **DIP** — репозиторий зависит от `EventDispatcherInterface`; listener broadcast — от `SalesOutletsReportStatsBroadcasterInterface`; audit log — от `Psr\Log\LoggerInterface`.

**Что не менялось для клиентов:** формат REST snapshot, канал `report-jobs.stats`, событие `.ReportJobStatsChanged`, payload `by_type` + `generated_at`.

**Важно при регистрации:** несколько listener'ов на одно событие регистрируются **отдельными** вызовами `Event::listen` (не массивом классов — иначе Laravel ожидает один invokable-listener):

```php
Event::listen(SalesOutletReportJobMutated::class, BroadcastReportJobStatsOnJobMutation::class);
Event::listen(SalesOutletReportJobMutated::class, LogSalesOutletReportJobMutation::class);
```

`findByUuid()` событие **не** диспатчит — только мутации (`create`, `updateStatus`).

#### Активные listeners

| Listener | Триггер | Действие |
|---|---|---|
| `BroadcastReportJobStatsOnJobMutation` | `SalesOutletReportJobMutated` | `aggregate()` из БД → broadcast `ReportJobStatsChanged` |
| `LogSalesOutletReportJobMutation` | то же | `LoggerInterface::info` с `uuid` (audit trail в логах) |

На один успешный CSV-job типично **3–4** mutation event (create → processing → completed) и столько же broadcast snapshot — осознанный компромисс «полный срез на каждое изменение».

#### Перспективы: куда наращивать через listeners

Тот же `SalesOutletReportJobMutated` — **единая точка расширения** для любых побочных эффектов после изменения задачи. Репозиторий и report pipeline трогать не нужно.

| Направление | Идея listener'а | Зависимости / примечания |
|---|---|---|
| **Debounce broadcast** | Coalesce нескольких мутаций одного job в один Reverb-push (500 ms / per-request) | Внутренний cache/queue; снижает нагрузку на Reverb и SQL `aggregate()` |
| **Метрики** | Счётчики Prometheus/OpenTelemetry: `report_jobs_mutated_total{status=...}` | `uuid` + повторный read status из репозитория или расширение payload события |
| **Audit в БД** | Запись в `report_job_audit_log` (who/when/uuid) | Отдельный repository; не смешивать с application log |
| **Уведомления** | Slack/Telegram при `failed` | Listener фильтрует по статусу после read job или слушает отдельное событие `SalesOutletReportJobFailed` (узче domain event) |
| **Cache invalidation** | Сброс Redis-ключа stats snapshot | Если REST stats начнут кешироваться |
| **Аналитика** | Отправка в очередь analytics (Segment, internal bus) | Async listener `ShouldQueue` |
| **Rate limiting / алерты** | Алерт при всплеске `failed` за минуту | Агрегация в listener + threshold |

**Когда выделять отдельное domain event** (вместо общего `SalesOutletReportJobMutated`):

- нужны **разные** listener'ы только на `failed` или только на `create` — например `SalesOutletReportJobFailed`, `SalesOutletReportJobCreated`;
- payload события должен нести `fromStatus` / `toStatus`, чтобы listener не ходил в БД повторно.

**Когда оставить один `SalesOutletReportJobMutated`:**

- все реакции одинаково актуальны на любую мутацию (как сейчас: full stats snapshot + log);
- минимум классов событий, один контракт «строка job изменилась».

#### Как добавить новый listener

1. Создать класс в `app/Listeners/` с методом `handle(SalesOutletReportJobMutated $event): void`.
2. Зависимости — только через constructor (контракты / `LoggerInterface`, не фасады в domain).
3. Зарегистрировать **отдельным** `Event::listen` в `AppServiceProvider::boot()`.
4. Unit-тест: mock зависимостей, один вызов `handle()`.
5. При необходимости — feature-тест с `Event::fake()` / проверкой побочного эффекта.

Пример каркаса:

```php
final class NotifyOnReportJobFailure
{
    public function __construct(
        private readonly SalesOutletsAsyncJobRepositoryInterface $jobs,
        private readonly NotificationSenderInterface $notifications,
    ) {}

    public function handle(SalesOutletReportJobMutated $event): void
    {
        $job = $this->jobs->findByUuid($event->uuid);

        if ($job?->status !== AsyncJobStatus::Failed) {
            return;
        }

        $this->notifications->sendReportFailed($job);
    }
}
```

Для тяжёлых операций реализуйте `Illuminate\Contracts\Queue\ShouldQueue` на listener'е — мутация в БД останется синхронной, реакция уйдёт в очередь `service-b-queue`.

См. также [.cursor/rules/service-b-reports-strategy.mdc](../.cursor/rules/service-b-reports-strategy.mdc) — зафиксированные решения по Strategy.

## Архитектура

Единый Report API построен на Strategy + узких контрактах (ISP/DIP). Legacy-слой Export/Mail удалён; все отчёты идут через `SalesOutletsReportController` и `BuildSalesOutletsReportJob`.

- Strategy-обработчики: `CsvDownloadReportStrategy`, `HtmlEmailReportStrategy`, `MaxMessageReportStrategy` (CSV build через `AbstractSalesOutletsCsvReportStrategy`);
- MAX-интеграция: `HttpMaxMessengerClient` + `LaravelReportMaxMessageSender` (`app/Services/Max/`, `app/Contracts/Max/`);
- Очередь: `BuildSalesOutletsReportJob` (контейнер `service-b-queue`, `php artisan queue:work --timeout=900 --tries=1`);
- Shared domain: `shared/sales-outlets-domain` (CSV writer, query filters);
- Хранилище задач: таблица `sales_outlet_report_jobs`, Eloquent `SalesOutletReportJob`, domain `SalesOutletAsyncJob`;
- Live-stats: domain event `SalesOutletReportJobMutated` → listeners → `ReportJobStatsChanged` (см. раздел выше).

### Поток обработки задачи

```mermaid
sequenceDiagram
  participant Client
  participant API as SalesOutletsReportController
  participant App as SalesOutletsReportApiService
  participant Repo as EloquentSalesOutletsReportJobRepository
  participant Q as BuildSalesOutletsReportJob
  participant W as SalesOutletsReportWorkerService
  participant Orch as SalesOutletsReportProcessingOrchestrator
  participant Strat as ReportStrategyExecutionService

  Client->>API: POST /reports
  API->>App: create(filters, userId, reportType)
  App->>Repo: create → SalesOutletReportJobMutated
  App->>Q: dispatch(uuid)
  Q->>W: processByUuid(uuid)
  W->>Orch: process(job)
  Orch->>Orch: markProcessing
  Orch->>Strat: execute → build + deliver
  Orch->>Orch: complete (file path / email)
  Repo->>Repo: updateStatus → SalesOutletReportJobMutated
```

При ошибке в очереди `SalesOutletsReportJobFailureHandler` помечает задачу `failed` (тоже мутация → broadcast stats).

### Слои и ключевые контракты

| Слой | Примеры | Контракты |
|---|---|---|
| HTTP | `SalesOutletsReportController`, `SalesOutletsReportStatsController`, `StoreSalesOutletReportRequest` | `SalesOutletsReportApiServiceInterface`, `SalesOutletsReportStatsRepositoryInterface` |
| Application | `SalesOutletsReportApiService`, `SalesOutletsReportDownloadService`, `SalesOutletsReportProcessingOrchestrator`, `ReportStrategyExecutionService`, `ReportJobLifecycleService` | `SalesOutletsReport*Interface`, `ReportJobLifecycleInterface`, `ReportStrategyExecutionInterface` |
| Worker | `BuildSalesOutletsReportJob`, `SalesOutletsReportWorkerService` | `SalesOutletsReportProcessorWorkerInterface`, `SalesOutletsReportJobFailureHandlerInterface` |
| Domain | `SalesOutletAsyncJob`, DTO, `AsyncJobStatus`, `SalesOutletsReportType` | — |
| Infra | `EloquentSalesOutletsReportJobRepository`, `EloquentSalesOutletsReportStatsRepository`, `LocalReportFileStorage`, listeners | `SalesOutletsAsyncJobRepositoryInterface`, `EventDispatcherInterface` |
| Integration | `ReportJobStatsChanged`, Reverb, Mailhog, `HttpMaxMessengerClient` | Broadcasting + MAX `https://platform-api.max.ru` |

`SalesOutletsReportJobProcessor` — тонкая обёртка над orchestrator (точка входа для worker).

Ключевые контракты Strategy:

- `SalesOutletsReportProcessingStrategyInterface` — `build()` + `deliver()` → `ReportDeliveryResult`;
- `SalesOutletsDownloadableReportStrategyInterface` — marker для типов с `/download`;
- `SalesOutletsReportStrategyResolverInterface` — выбор стратегии по `report_type`;
- `SalesOutletsReportDownloadCapabilityInterface` — `supportsDownload()` для download-сервиса.

`SalesOutletsReportStrategyRegistry` — singleton с alias на resolver, capability и presentation (ISP: download-сервис не видит `resolve()`).

### Зафиксированные архитектурные решения

| Решение | Зачем |
|---|---|
| Orchestrator (lifecycle → strategy → completion) | SRP: processor/worker не содержат шагов pipeline |
| Strategy marker `SalesOutletsDownloadableReportStrategyInterface` | Download capability на уровне типа; orchestrator не ветвится по форматам |
| Triple-alias registry (resolver / capability / presentation) | ISP: download-сервис не видит `resolve()` |
| `ReportDeliveryResult` из `deliver()` | OCP доставки: file vs email без правок orchestrator |
| `SalesOutletReportJobMutated` + listeners | OCP побочных эффектов stats/log; persistence отделён от реакций |
| `EventDispatcherInterface` вместо `event()` в application | DIP для тестов и подмены bus |
| `SalesOutletsReportStatsRepositoryInterface` | REST stats и broadcast читают агрегаты через один контракт |
| Gateway auth через `GatewayUserResolverInterface` + DTO | `$request->user()` — реальный Eloquent `User` |

Не «упрощать» без явного запроса: объединять registry-интерфейсы, переносить `supportsDownload()` в orchestrator/processor, вшивать broadcast stats в репозиторий вместо listeners.

## Конфигурация

Основной файл: `config/sales_outlets_reports.php`.

| Секция | Ключ | Назначение |
|---|---|---|
| корень | `storage_disk` | Диск хранения CSV (`FILESYSTEM_DISK` / `SALES_OUTLETS_REPORTS_STORAGE_DISK`) |
| корень | `apply_fake_delay_environments` | Окружения с искусственной задержкой (`local`, `testing`) |
| `types.csv_download` | `fake_delay_seconds` | Задержка перед завершением CSV в dev |
| `types.html_email` | `recipients`, `subject`, `fake_delay_seconds` | Email-получатели, тема и задержка |
| `types.max_message` | `bot_access_token`, `chat_ids`, `user_ids`, `intro`, `max_text_length`, rate-limit/retry, `attachment_not_ready_retry_*` | MAX: токен, получатели, intro (≤4000), upload + messages |

Переменные окружения:

| Переменная | Назначение |
|---|---|
| `SALES_OUTLETS_REPORTS_STORAGE_DISK` | Диск для CSV-файлов |
| `SALES_OUTLETS_EXPORT_STORAGE_DISK` | Legacy fallback для `storage_disk` |
| `SALES_OUTLETS_EXPORT_FAKE_DELAY_SECONDS` | Задержка `csv_download` |
| `SALES_OUTLETS_MAIL_RECIPIENTS` | Список через запятую |
| `SALES_OUTLETS_MAIL_SUBJECT` | Тема письма |
| `SALES_OUTLETS_MAIL_FAKE_DELAY_SECONDS` | Задержка `html_email` |
| `BROADCAST_CONNECTION=reverb` | Live-stats (обязательно в Docker) |
| `REVERB_HOST=reverb` | Внутри compose-сети (см. корневой `docker-compose.yml`) |
| `MAIL_HOST=mailhog` | SMTP для `html_email` в dev |
| `MAX_BOT_ACCESS_TOKEN` | Access token бота MAX (только в `.env`, не в git) |
| `MAX_REPORT_CHAT_IDS` | Список `chat_id` через запятую |
| `MAX_REPORT_USER_IDS` | Список `user_id` через запятую |
| `MAX_REPORT_INTRO` | Текст сообщения (CSV прикрепляется отдельно) |
| `MAX_MESSAGE_FAKE_DELAY_SECONDS` | Искусственная задержка `max_message` в `local`/`testing` |

Для `html_email` в локальной среде используется Mailhog: `http://localhost:8025`.

### Безопасность токена MAX

- Токен передаётся **только** в заголовке `Authorization` ([обзор API](https://dev.max.ru/docs-api)); не логируется, не попадает в `error_message` job и не отдаётся на фронт.
- Платформа может **отозвать** токен — все запросы вернут **401**; job завершается `failed` **без** автоматического retry.
- Ротация: новый токен в кабинете MAX → обновить `MAX_BOT_ACCESS_TOKEN` в `.env` `service-b` → `php artisan config:clear` → перезапуск `service-b-queue`.

### Лимиты MAX API (справочник для `max_message`)

| Категория | Лимит | Поведение в сервисе |
|---|---|---|
| Intro-текст | ≤ **4000** символов | `MAX_REPORT_INTRO`; при превышении — `InvalidArgumentException` в `LaravelReportMaxMessageSender` |
| Данные отчёта | без лимита 4000 | Полный CSV во вложении (`AbstractSalesOutletsCsvReportStrategy::build`) |
| Rate limit | ~**30 rps** | `inter_recipient_delay_ms`; retry **429** / **503**; retry «attachment not ready» |
| Auth | только `Authorization` | **401** → `MaxMessengerAuthException`, job `failed`, без retry |
| Получатель | один `chat_id` **или** `user_id` на запрос | N получателей = N вызовов `POST /messages` (один upload на job) |
| Формат | intro + CSV file | Имя файла: `objects-sales-outlets.csv` или `objects-sales-outlets-{userId}.csv` |

Исходящий HTTPS из контейнера `service-b` к `https://platform-api.max.ru` обычно не требует доработок Docker.

## Пример: стратегия `xls_email` (XLS во вложении)

> **Важно:** это архитектурный пример расширения (roadmap/blueprint), а не реализованный тип.  
> Production-типы: `csv_download`, `html_email`, `max_message` (см. `SalesOutletsReportType`).

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

### 4. Письмо: сопроводительный текст + вложение

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

### 5. Регистрация в DI

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

### 6. Запрос API

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

## Локальный запуск

Из корня репозитория (минимум для отчётов и live-stats):

```bash
docker compose up -d service-b service-b-queue reverb redis mailhog
```

| Сервис | Порт / URL |
|---|---|
| `service-b` API | `http://localhost:8082` (`SERVICE_B_PORT`) |
| Gateway → API | `http://localhost:8080/api/b/...` |
| Mailhog | `http://localhost:8025` |

Пароль и хост MySQL для `service-b` задаются в корневом `docker-compose.yml` (`SERVICE_B_DB_*`). После первого запуска: `php artisan key:generate`, миграции (с согласия), `composer install` при изменении `composer.lock`.

Полный стек и первичная настройка — в [корневом README](../README.md).

## Manual QA MAX (`max_message`)

Ручная проверка выполняется **после** деплоя кода `max_message` на dev-стенд, с **реальным** ботом и токеном в `.env` контейнера `service-b` (значение **не** коммитить). Автотесты CI вызывают только `Http::fake` — сценарии ниже **не** входят в `php artisan test`.

### Подготовка

1. Бот добавлен в целевые чаты (`MAX_REPORT_CHAT_IDS`) и/или начат диалог для `user_id`.
2. В `service-b/.env` заданы валидные `MAX_BOT_ACCESS_TOKEN`, минимум один непустой список `MAX_REPORT_CHAT_IDS` и/или `MAX_REPORT_USER_IDS`.
3. Запущены: `service-b`, `service-b-queue`, `main-app`, `reverb`, `mailhog` (для сценария 6).
4. Пользователь авторизован в `main-app`, открыта страница `/objects-sales-outlets-2` (Dark UI).

Проверка конфигурации в контейнере (токен не выводить в лог/чат):

```bash
docker compose exec -T service-b php artisan tinker --execute="echo config('sales_outlets_reports.types.max_message.bot_access_token') !== '' ? 'token:set' : 'token:empty';"
```

### Чеклист (таблица)

Заполняйте колонку **Факт** после прогона (дата, скрин/ссылка, OK/FAIL).

| № | Сценарий | Действия | Ожидаемый результат | Факт |
|---|---|---|---|---|
| 1 | **Конфиг токена** | `tinker` / проверка `.env`: `MAX_BOT_ACCESS_TOKEN` не пуст | `token:set`; без токена job уйдёт в `failed` (401) | |
| 2 | **CSV, мало данных** | UI: узкий фильтр (2–5 строк), 2–3 колонки → «Отправить в MAX»; poll до `completed` | В MAX: intro (`MAX_REPORT_INTRO`) + **CSV-вложение** с заголовками и строками | |
| 3 | **Большой отчёт** | Широкий фильтр (много строк) → MAX | Тот же intro; CSV содержит **все** строки выборки; job `completed` | |
| 4 | **401 — неверный/отозванный токен** | В `.env` подставить заведомо неверный `MAX_BOT_ACCESS_TOKEN` → `config:clear` → перезапуск queue → отправка с UI | Job `failed`; poll/UI: понятный текст (**без** значения токена), смысл «обновите MAX_BOT_ACCESS_TOKEN / обратитесь к администратору»; в логах `service-b` **нет** токена в URL/body | |
| 5 | **Несколько `chat_id`** | `MAX_REPORT_CHAT_IDS=id1,id2` (оба чата с ботом), валидный токен → одна отправка с UI | **Оба** чата получили **одинаковое** сообщение (2 успешных `POST /messages`); при fail на первом получателе второй **не** должен получить сообщение (401) | |
| 6 | **Сверка с MailHog** | Тот же фильтр и набор колонок: сначала «Отправить на почту» (`html_email`), затем «Отправить в MAX» | Данные в письме (HTML) и в CSV из MAX **совпадают** | |

### Детальные шаги по ключевым сценариям

#### 2. CSV-вложение в чате

- В Network: `POST /objects-sales-outlets-2/max` с теми же `columns`, что в таблице UI.
- После `completed`: в MAX — intro-текст; скачать вложение `.csv` — UTF-8 BOM, заголовки и строки совпадают с `csv_download` при тех же фильтрах.

#### 3. Большой объём данных

- CSV-файл должен содержать полный набор строк (без усечения intro; лимит 4000 символов только на `MAX_REPORT_INTRO`).
- При таймаутах queue увеличить `--timeout` worker (в compose уже 900 с).

#### 4. Ошибка 401

```bash
# в service-b/.env: MAX_BOT_ACCESS_TOKEN=invalid-for-qa-only
docker compose exec -T service-b php artisan config:clear
docker compose restart service-b-queue
```

- Poll: `GET /objects-sales-outlets-2/max/{uuid}` → `status: failed`, `error_message` без подстроки токена.
- Вернуть валидный токен после проверки.

#### 5. Два `chat_id`

```env
MAX_REPORT_CHAT_IDS=123456789,987654321
```

- Оба ID из `GET /chats` или кабинета MAX; бот — участник обоих чатов.
- Один клик «Отправить в MAX» → два сообщения с идентичным содержимым.

#### 6. Сравнение с `html_email`

1. MailHog: `http://localhost:8025` — открыть последнее письмо с HTML-таблицей.
2. MAX — то же сообщение по фильтру.
3. Сравнить первые строки и значения ячеек в CSV и в HTML-письме.

### API-прогон без UI (опционально)

Для изоляции `service-b` от `main-app`:

```http
POST /api/b/sales-outlets/reports
X-User-Id: 1
Content-Type: application/json

{
  "report_type": "max_message",
  "columns": ["id", "shop", "status"]
}
```

Далее `GET /api/b/sales-outlets/reports/{uuid}` до `completed` или `failed`.

### Что не проверяется вручную в v1

- Реальный burst **429** при 30+ rps (нагрузка).
- HTML-таблица в теле MAX-сообщения (только intro + CSV).
- Отдельная Artisan-команда проверки токена (`max:ping` не реализована).
- Дополнительные форматы вложений (XLS) через MAX Upload API.

## Стенд UI MAX (приветствие + кнопки + messMax)

Отдельный модуль для проверки **входящих** событий MAX: inline-клавиатура «да»/«нет», webhook `message_callback` / `bot_started`, лог канала `messMax`. **Не связан** с отчётами `max_message` (Strategy sales outlets).

| Отличие | `max_message` (отчёты) | Стенд UI MAX |
|---|---|---|
| Направление | Исходящее: CSV + intro | Входящее: webhook + ответ на кнопки |
| Лог | default-канал (HTTP debug) | **только** `Log::channel('messMax')` → `storage/logs/messMax.log` |
| Получатели | `MAX_REPORT_CHAT_IDS` / `MAX_REPORT_USER_IDS` | Те же env (reuse) |
| Токен бота | `MAX_BOT_ACCESS_TOKEN` | Тот же |
| Gateway | Через `nginx-gateway` (`/api/b/...`) | **Напрямую** на `service-b:8082` (`POST /api/webhooks/max`) |

Конфиг: `config/max_ui_stand.php`. Ключевые env: `MAX_UI_STAND_GREETING`, `MAX_WEBHOOK_URL`, `MAX_WEBHOOK_SECRET` (минимум 5 символов).

MAX требует **HTTPS:443** для webhook — в dev нужен публичный HTTPS-туннель на порт `SERVICE_B_PORT` (по умолчанию **8082**).

В РФ **ngrok**, **VK Tunnel** (закрыт с 10.2025) и **cloudflared Quick Tunnel** (`trycloudflare.com`) часто недоступны. Рекомендуется **fxTunnel**:

```bash
curl -fsSL https://fxtun.ru/install.sh | sh
export FXTUN_TOKEN=sk_...   # https://fxtun.ru → личный кабинет
./scripts/fxtun-tunnel.sh
# MAX_WEBHOOK_URL=https://<субдомен>.fxtun.ru/api/webhooks/max
```

Запасной вариант — **cloudflared** (если `api.trycloudflare.com` открывается, или через VPN):

```bash
./scripts/cloudflared-tunnel.sh install
./scripts/cloudflared-tunnel.sh
```

### Чеклист QA (ручная проверка)

| Шаг | Действие | Ожидаемый результат |
|---|---|---|
| 1 | `./scripts/fxtun-tunnel.sh` (или cloudflared/VPN) | Публичный HTTPS URL |
| 2 | В `service-b/.env`: `MAX_WEBHOOK_URL=https://<host>/api/webhooks/max`, `MAX_WEBHOOK_SECRET=...` (≥5 символов), `MAX_BOT_ACCESS_TOKEN`, `MAX_REPORT_CHAT_IDS` и/или `MAX_REPORT_USER_IDS` | Конфиг загружен |
| 3 | `docker compose exec -T service-b php artisan max:webhook:subscribe` | Подписка на `message_callback`, `bot_started` |
| 4 | `docker compose exec -T service-b php artisan max:ui-stand:send` | В MAX: приветствие + кнопки «да» / «нет» |
| 5 | Нажать кнопку в MAX | В `messMax.log`: `MAX button clicked`, `answer`: «да» или «нет» |
| 6 | `docker compose exec -T service-b tail -f storage/logs/messMax.log` | События стенда без токена бота |

Пример команд:

```bash
# 0. Установка cloudflared (один раз, из корня репозитория)
./scripts/cloudflared-tunnel.sh install
# Скрипт качает .deb с pkg.cloudflare.com (GitHub в РФ часто недоступен).
# Вручную:
# curl -L -o /tmp/cloudflared.deb \
#   https://pkg.cloudflare.com/cloudflared/pool/main/c/cloudflared/cloudflared_2025.4.2_amd64.deb
# mkdir -p /tmp/cf && dpkg-deb -x /tmp/cloudflared.deb /tmp/cf
# cp /tmp/cf/usr/bin/cloudflared ~/.local/bin/cloudflared && chmod +x ~/.local/bin/cloudflared

# 1. Туннель (в отдельном терминале; service-b должен быть запущен)
./scripts/cloudflared-tunnel.sh
# альтернатива через Docker:
# CLOUDFLARED_USE_DOCKER=1 ./scripts/cloudflared-tunnel.sh

# 2. Подписка и отправка приветствия (скопируйте HTTPS URL из вывода туннеля)
docker compose exec -T service-b php artisan max:webhook:subscribe
docker compose exec -T service-b php artisan max:ui-stand:send

# 3. Просмотр лога стенда
docker compose exec -T service-b tail -f storage/logs/messMax.log
```

Автотесты CI используют `Http::fake` и перехват `Log::channel('messMax')` — **без** реальных вызовов MAX API и без cloudflared.

## Тесты

```bash
# из корня репозитория
docker compose exec -T service-b php artisan test

# или через общий скрипт (пересоздаёт sail_db_testing)
./scripts/test-services.sh service-b
```

Тестовая БД: **`sail_db_testing`** (см. `phpunit.xml`, `.env.testing`). Не указывайте рабочую БД в тестовом окружении.

Покрытие по областям:

| Область | Тесты |
|---|---|
| Report API (все `report_type`, в т.ч. `max_message` с `Http::fake`) | `tests/Feature/SalesOutletsReportTest.php` |
| Live-stats + domain events | `tests/Feature/SalesOutletsReportStatsTest.php` |
| Strategy / registry / download / orchestrator | `tests/Unit/*ReportStrategy*`, `*Orchestrator*`, `*Download*` |
| MAX config keys | `tests/Unit/SalesOutletsReportsConfigKeysTest.php` |
| Listeners broadcast / log | `tests/Unit/BroadcastReportJobStatsOnJobMutationTest.php`, `LogSalesOutletReportJobMutationTest.php` |
| Gateway auth | `tests/Unit/TrustGatewayAuthTest.php` |
| Стенд UI MAX (inline keyboard, webhook, messMax) | `tests/Unit/MaxUiStandGreetingSenderTest.php`, `MaxCallbackHandlerTest.php`, `VerifyMaxWebhookSecretTest.php`, `tests/Feature/MaxWebhookControllerTest.php` |

Запуск в Docker (PHP 8.4+ в образе; локальный WSL PHP 8.3 для `artisan` не подходит — см. [REFACTORING-CHECKLIST](./REFACTORING-CHECKLIST.md)).
