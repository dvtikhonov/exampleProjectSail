<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Очистка устаревших dev-туннелей из подписок MAX webhook.
 */
interface MaxWebhookStaleDevTunnelCleanerInterface
{
    /**
     * Удаляет устаревшие dev-туннели, сохраняя текущий URL.
     *
     * @return array{removed: list<string>, preserved: list<string>}
     */
    public function unsubscribeStaleDevTunnels(string $configuredUrl): array;
}
