<?php

declare(strict_types=1);

/**
 * Настройки модуля синхронизации Briskly (сессии, токен, sidecar, лимиты).
 */
return [
    /** TTL Bearer Briskly в кэше (не в БД plaintext). */
    'token_ttl_seconds' => (int) env('BRISKLY_SYNC_TOKEN_TTL', 7200),

    /** HTTP sidecar Node orchestrator (match). */
    'orchestrator_base_url' => (string) env(
        'BRISKLY_SYNC_ORCHESTRATOR_URL',
        'http://127.0.0.1:8791',
    ),

    /** База Briskly company API. */
    'briskly_base_url' => (string) env(
        'BRISKLY_API_BASE_URL',
        'https://briskly.business/api/company',
    ),

    /** Лимит строк на секцию sync-results / apply (2B). */
    'section_cap' => 25,

    /** Порог относительной Δ цены для confirm_large_delta. */
    'large_delta_ratio' => 0.5,

    /** Максимум страниц snapshot get-list. */
    'snapshot_max_pages' => (int) env('BRISKLY_SYNC_SNAPSHOT_MAX_PAGES', 50),

    /** Размер страницы get-list. */
    'snapshot_page_limit' => (int) env('BRISKLY_SYNC_SNAPSHOT_PAGE_LIMIT', 100),

    /** Пауза между страницами Briskly (мс). */
    'briskly_delay_ms' => (int) env('BRISKLY_SYNC_DELAY_MS', 200),

    /** Timeout HTTP к оркестратору (сек). */
    'orchestrator_timeout_seconds' => (int) env('BRISKLY_SYNC_ORCHESTRATOR_TIMEOUT', 120),

    /** Timeout HTTP к Briskly (сек). */
    'briskly_timeout_seconds' => (int) env('BRISKLY_SYNC_BRISKLY_TIMEOUT', 30),

    /** TTL lock на apply (сек). */
    'apply_lock_ttl_seconds' => 120,
];
