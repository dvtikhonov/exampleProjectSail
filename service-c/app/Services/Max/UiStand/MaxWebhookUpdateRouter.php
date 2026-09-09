<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxWebhookUpdateHandlerInterface;
use App\Contracts\Max\MaxWebhookUpdateRouterInterface;
use Psr\Log\LoggerInterface;

/**
 * Маршрутизация webhook-обновлений MAX по типу события через реестр handlers.
 */
final class MaxWebhookUpdateRouter implements MaxWebhookUpdateRouterInterface
{
    /**
     * @param  array<string, MaxWebhookUpdateHandlerInterface>  $handlers
     */
    public function __construct(
        private readonly array $handlers,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Обрабатывает входящее webhook-обновление MAX.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $updateType = (string) ($payload['update_type'] ?? '');

        $this->logger->info('MAX webhook received', [
            'update_type' => $updateType !== '' ? $updateType : 'unknown',
        ]);

        $handler = $this->handlers[$updateType] ?? null;

        if ($handler === null) {
            return;
        }

        $handler->handle($payload);
    }
}
