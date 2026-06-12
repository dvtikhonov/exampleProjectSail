<?php

namespace App\Services\Max\UiStand;

use App\DTO\Max\MaxCallbackUpdateDto;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

class MaxCallbackHandler
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly Repository $config,
    ) {}

    public function handle(MaxCallbackUpdateDto $update): void
    {
        $yesPayload = (string) $this->config->get('max.ui_stand.button_yes_payload', 'yes');
        $answerLabel = $update->payload === $yesPayload ? 'да' : 'нет';
        $responseText = "Вы нажали кнопку: {$answerLabel}";

        Log::channel('messMax')->info('MAX button clicked', [
            'answer' => $answerLabel,
            'payload' => $update->payload,
            'callback_id' => $update->callbackId,
            'user_id' => $update->userId,
            'chat_id' => $update->chatId,
        ]);

        try {
            $this->client->answerCallback(
                callbackId: $update->callbackId,
                messageText: $responseText,
            );

            Log::channel('messMax')->info('MAX callback answered', [
                'callback_id' => $update->callbackId,
            ]);
        } catch (MaxMessengerException $exception) {
            Log::channel('messMax')->warning('MAX callback answer failed, retrying with notification only', [
                'callback_id' => $update->callbackId,
                'error' => $exception->userMessage(),
            ]);

            try {
                $this->client->answerCallback(
                    callbackId: $update->callbackId,
                    notification: $responseText,
                );

                Log::channel('messMax')->info('MAX callback answered with notification', [
                    'callback_id' => $update->callbackId,
                ]);
            } catch (Throwable $retryException) {
                Log::channel('messMax')->warning('MAX callback notification failed, retrying empty answer', [
                    'callback_id' => $update->callbackId,
                    'error' => $retryException instanceof MaxMessengerException
                        ? $retryException->userMessage()
                        : $retryException->getMessage(),
                ]);

                try {
                    $this->client->answerCallback($update->callbackId);

                    Log::channel('messMax')->info('MAX callback answered without payload', [
                        'callback_id' => $update->callbackId,
                    ]);
                } catch (Throwable $finalException) {
                    Log::channel('messMax')->error('MAX callback answer failed', [
                        'callback_id' => $update->callbackId,
                        'error' => $finalException instanceof MaxMessengerException
                            ? $finalException->userMessage()
                            : $finalException->getMessage(),
                    ]);
                }
            }
        }
    }
}
