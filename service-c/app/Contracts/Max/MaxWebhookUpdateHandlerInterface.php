<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Обработчик одного типа webhook-обновления MAX.
 */
interface MaxWebhookUpdateHandlerInterface
{
    /**
     * Обрабатывает payload webhook-обновления.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
