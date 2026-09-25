<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Handlers;

use App\Contracts\Max\MaxWebhookUpdateHandlerInterface;
use App\Modules\MaxIncomingRelay\Contracts\IncomingMessageRelayServiceInterface;
use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;

/**
 * Обработка webhook-события message_created: пересылка текста в Home_chat.
 */
final class MessageCreatedUpdateHandler implements MaxWebhookUpdateHandlerInterface
{
    public function __construct(
        private readonly IncomingMessageRelayServiceInterface $relayService,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle(array $payload): void
    {
        $dto = IncomingBotMessageDto::tryFrom($payload);
        if ($dto === null) {
            return;
        }

        $this->relayService->relay($dto);
    }
}
