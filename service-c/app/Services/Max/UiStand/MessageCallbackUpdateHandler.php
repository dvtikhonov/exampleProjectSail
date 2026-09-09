<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxCallbackHandlerInterface;
use App\Contracts\Max\MaxUiStandRecipientRegistryInterface;
use App\Contracts\Max\MaxWebhookUpdateHandlerInterface;
use App\DTO\Max\MaxCallbackUpdateDto;

/**
 * Обработка webhook-события message_callback.
 */
final class MessageCallbackUpdateHandler implements MaxWebhookUpdateHandlerInterface
{
    public function __construct(
        private readonly MaxCallbackHandlerInterface $callbackHandler,
        private readonly MaxUiStandRecipientRegistryInterface $recipientRegistry,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function handle(array $payload): void
    {
        $callback = $payload['callback'] ?? [];

        if (! is_array($callback)) {
            return;
        }

        $callbackId = (string) ($callback['callback_id'] ?? '');
        $buttonPayload = (string) ($callback['payload'] ?? '');

        if ($callbackId === '') {
            return;
        }

        $userId = isset($callback['user']['user_id']) ? (int) $callback['user']['user_id'] : null;
        $message = $payload['message'] ?? [];
        $recipient = is_array($message) ? ($message['recipient'] ?? []) : [];
        $chatId = is_array($recipient) && isset($recipient['chat_id'])
            ? (int) $recipient['chat_id']
            : null;

        if ($chatId !== null && $chatId !== 0) {
            $this->recipientRegistry->rememberChatId($chatId);
        } elseif ($userId !== null && $userId > 0) {
            $this->recipientRegistry->rememberUserId($userId);
        }

        if ($userId !== null) {
            $this->callbackHandler->handle(new MaxCallbackUpdateDto(
                callbackId: $callbackId,
                payload: $buttonPayload,
                userId: $userId,
            ));

            return;
        }

        if ($chatId !== null) {
            $this->callbackHandler->handle(new MaxCallbackUpdateDto(
                callbackId: $callbackId,
                payload: $buttonPayload,
                chatId: $chatId,
            ));
        }
    }
}
