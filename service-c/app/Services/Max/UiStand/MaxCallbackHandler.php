<?php

declare(strict_types=1);

namespace App\Services\Max\UiStand;

use App\Contracts\Shared\ApplicationConfigInterface;
use App\DTO\Max\MaxCallbackUpdateDto;
use Psr\Log\LoggerInterface;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;
use Throwable;

/**
 * Обработка нажатий inline-кнопок стенда MAX.
 */
class MaxCallbackHandler
{
    public function __construct(
        private readonly MaxMessengerClientInterface $client,
        private readonly ApplicationConfigInterface $config,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Отвечает на callback кнопки сообщением или уведомлением.
     */
    public function handle(MaxCallbackUpdateDto $update): void
    {
        $yesPayload = (string) $this->config->get('max.ui_stand.button_yes_payload', 'yes');
        $answerLabel = $update->payload === $yesPayload ? 'да' : 'нет';
        $responseText = "Вы нажали кнопку: {$answerLabel}";

        $this->logger->info('MAX button clicked', [
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

            $this->logger->info('MAX callback answered', [
                'callback_id' => $update->callbackId,
            ]);
        } catch (MaxMessengerException $exception) {
            $this->logger->warning('MAX callback answer failed, retrying with notification only', [
                'callback_id' => $update->callbackId,
                'error' => $exception->userMessage(),
            ]);

            try {
                $this->client->answerCallback(
                    callbackId: $update->callbackId,
                    notification: $responseText,
                );

                $this->logger->info('MAX callback answered with notification', [
                    'callback_id' => $update->callbackId,
                ]);
            } catch (Throwable $retryException) {
                $this->logger->warning('MAX callback notification failed, retrying empty answer', [
                    'callback_id' => $update->callbackId,
                    'error' => $retryException instanceof MaxMessengerException
                        ? $retryException->userMessage()
                        : $retryException->getMessage(),
                ]);

                try {
                    $this->client->answerCallback($update->callbackId);

                    $this->logger->info('MAX callback answered without payload', [
                        'callback_id' => $update->callbackId,
                    ]);
                } catch (Throwable $finalException) {
                    $this->logger->error('MAX callback answer failed', [
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
