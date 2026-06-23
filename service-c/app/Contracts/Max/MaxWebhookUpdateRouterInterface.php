<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Маршрутизация входящих webhook-обновлений MAX.
 */
interface MaxWebhookUpdateRouterInterface
{
    /**
     * Обрабатывает входящее webhook-обновление MAX.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
