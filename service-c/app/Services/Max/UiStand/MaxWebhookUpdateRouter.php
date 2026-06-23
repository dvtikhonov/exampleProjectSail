<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Max\MaxWebhookUpdateRouterInterface;
use App\DTO\Max\MaxCallbackUpdateDto;
use Illuminate\Support\Facades\Log;

/**
 * Маршрутизация webhook-обновлений MAX по типу события.
 */
final class MaxWebhookUpdateRouter implements MaxWebhookUpdateRouterInterface
{
    public function __construct(
        private readonly MaxCallbackHandler $callbackHandler,
        private readonly MaxUiStandGreetingSender $greetingSender,
    ) {}

    /**
     * Обрабатывает входящее webhook-обновление MAX.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $updateType = (string) ($payload['update_type'] ?? '');

        Log::channel('messMax')->info('MAX webhook received', [
            'update_type' => $updateType !== '' ? $updateType : 'unknown',
        ]);

        match ($updateType) {
            'message_callback' => $this->handleMessageCallback($payload),
            'bot_started' => $this->handleBotStarted($payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleMessageCallback(array $payload): void
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
        $chatId = isset($payload['message']['recipient']['chat_id'])
            ? (int) $payload['message']['recipient']['chat_id']
            : null;

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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleBotStarted(array $payload): void
    {
        $userId = 0;

        if (isset($payload['user']['user_id'])) {
            $userId = (int) $payload['user']['user_id'];
        } elseif (isset($payload['user_id'])) {
            $userId = (int) $payload['user_id'];
        }

        Log::channel('messMax')->info('bot_started', ['user_id' => $userId]);

        if ($userId > 0) {
            $this->greetingSender->sendToUser($userId);
        }
    }
}
