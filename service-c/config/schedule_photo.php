<?php

declare(strict_types=1);

use App\Support\Briskly\BrisklySyncSourceOrigin;

/**
 * Настройки модуля SchedulePhoto (фото графика → OCR sidecar → match → apply).
 *
 * SSRF: probe PhotoText ходит только на origin из allowed_base_urls (токен на сервере).
 */
$localBaseUrl = rtrim((string) env(
    'SCHEDULE_PHOTO_LOCAL_BASE_URL',
    'http://host.docker.internal:8083',
), '/');

/** Тот же origin, что briskly_sync.source_base_url (из MAX_MINI_APP_URL). */
$remoteBaseUrl = BrisklySyncSourceOrigin::fromMiniAppUrl((string) env('MAX_MINI_APP_URL', ''));

$defaultBaseUrl = rtrim((string) env(
    'SCHEDULE_PHOTO_DEFAULT_BASE_URL',
    $localBaseUrl !== '' ? $localBaseUrl : 'http://host.docker.internal:8083',
), '/');

$extraAllowed = array_values(array_filter(array_map(
    static fn (string $url): string => rtrim(trim($url), '/'),
    explode(',', (string) env('SCHEDULE_PHOTO_ALLOWED_BASE_URLS', '')),
), static fn (string $url): bool => $url !== ''));

$allowedBaseUrls = array_values(array_unique(array_filter(
    array_merge(
        [
            $localBaseUrl,
            // Совместимость с локальным PHP вне Docker и существующими тестами.
            'http://localhost:8083',
            $remoteBaseUrl,
            $defaultBaseUrl,
        ],
        $extraAllowed,
    ),
    static fn (string $url): bool => $url !== '',
)));

return [
    /**
     * Default BASE_URL PhotoText для формы (локально host.docker.internal:8083).
     */
    'default_base_url' => $defaultBaseUrl,

    /**
     * Локальный origin для выбора в UI (PHP в Docker → host service-c).
     */
    'local_base_url' => $localBaseUrl,

    /**
     * Allow-list origin’ов для readiness/start (exact match после rtrim `/`).
     * Базово: local Docker + localhost:8083 + origin из MAX_MINI_APP_URL.
     * Дополнительно: SCHEDULE_PHOTO_ALLOWED_BASE_URLS (через запятую).
     *
     * @var list<string>
     */
    'allowed_base_urls' => $allowedBaseUrls,

    /**
     * HTTP sidecar Node orchestrator (schedule-ocr + /health).
     * Локально обычно совпадает с BRISKLY_SYNC_ORCHESTRATOR_URL (8791).
     */
    'orchestrator_base_url' => (string) env(
        'SCHEDULE_PHOTO_ORCHESTRATOR_URL',
        'http://127.0.0.1:8791',
    ),

    /** Timeout handshake POST /schedule-ocr (сек). */
    'orchestrator_handshake_timeout_seconds' => (int) env(
        'SCHEDULE_PHOTO_ORCHESTRATOR_HANDSHAKE_TIMEOUT',
        60,
    ),

    /** Timeout GET /health sidecar (сек). */
    'sidecar_health_timeout_seconds' => (int) env('SCHEDULE_PHOTO_SIDECAR_HEALTH_TIMEOUT', 5),

    /** Timeout probe GET PhotoText /restaurants (сек). */
    'phototext_probe_timeout_seconds' => (int) env('SCHEDULE_PHOTO_PHOTOTEXT_PROBE_TIMEOUT', 10),

    /**
     * Секрет колбэка sidecar (заголовок X-Schedule-Photo-Capture-Secret).
     */
    'capture_secret' => (string) env('SCHEDULE_PHOTO_CAPTURE_SECRET', ''),

    /**
     * Явный путь к phototext-dish-aliases.md (опционально).
     * Иначе: .cursor/commands/phototext-dish-aliases.md → service-c/phototext-dish-aliases.md.
     * Без БД: resolve-discarded дописывает новую пару OCR→канон в md.
     */
    'aliases_path' => (string) env('SCHEDULE_PHOTO_ALIASES_PATH', ''),

    /**
     * Диск Laravel для временных фото OCR.
     * Один инстанс и sidecar на том же хосте — local.
     * Второй инстанс подключает этот каталог общим volume, не вторым локальным диском.
     */
    'photo_disk' => (string) env('SCHEDULE_PHOTO_PHOTO_DISK', 'local'),

    /** Каталог на диске для фото прогонов. */
    'photo_directory' => (string) env('SCHEDULE_PHOTO_PHOTO_DIRECTORY', 'schedule-photo'),
];
