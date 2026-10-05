<?php

declare(strict_types=1);

use App\Support\Briskly\BrisklySyncSourceOrigin;

/**
 * Настройки модуля синхронизации Briskly (сессии, токен, sidecar, лимиты).
 *
 * Remote source (не-prod): origin из MAX_MINI_APP_URL; auth — PHOTOTEXT_AGENT_TOKEN
 * (config phototext.agent_token). На prod (host APP_URL = host mini-app) — local.
 */
$sourceBaseUrl = BrisklySyncSourceOrigin::fromMiniAppUrl((string) env('MAX_MINI_APP_URL', ''));

return [
    /** TTL Bearer Briskly в кэше (не в БД plaintext). */
    'token_ttl_seconds' => (int) env('BRISKLY_SYNC_TOKEN_TTL', 7200),

    /**
     * Origin VPS source (scheme+host[+port]) из MAX_MINI_APP_URL; path /max-app отбрасывается.
     */
    'source_base_url' => $sourceBaseUrl,

    /**
     * Читать source с remote PhotoText, если origin задан и host ≠ host APP_URL.
     */
    'source_remote' => BrisklySyncSourceOrigin::isRemote(
        $sourceBaseUrl,
        (string) env('APP_URL', ''),
    ),

    /** Timeout HTTP к remote VPS source (сек). */
    'source_timeout_seconds' => (int) env('BRISKLY_SYNC_SOURCE_TIMEOUT', 30),

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

    /**
     * Общий секрет sidecar ↔ service-c для POST /capture-token.
     * Пустое значение отключает capture (503).
     */
    'capture_secret' => (string) env('BRISKLY_SYNC_CAPTURE_SECRET', ''),

    /** Timeout HTTP к sidecar capture-token (сек). */
    'capture_timeout_seconds' => (int) env('BRISKLY_SYNC_CAPTURE_TIMEOUT', 30),

    /** TTL lock на apply (сек). */
    'apply_lock_ttl_seconds' => 120,
];
