<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Проба доступности настроенного MAX_WEBHOOK_URL.
 */
interface MaxWebhookUrlProbeInterface
{
    /**
     * Проверяет доступность настроенного MAX_WEBHOOK_URL.
     *
     * @return array{url: string, http_status: int|null, reachable: bool, error: string|null}
     */
    public function probeWebhookUrl(): array;
}
