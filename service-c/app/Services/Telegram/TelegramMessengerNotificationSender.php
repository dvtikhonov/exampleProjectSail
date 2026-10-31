<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\Telegram\TelegramMessengerClientInterface;
use App\Contracts\Telegram\TelegramMessengerNotificationSenderInterface;
use App\Exceptions\Telegram\TelegramMessengerException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Единая отправка Telegram-уведомлений с обработкой ошибок и логированием.
 */
final class TelegramMessengerNotificationSender implements TelegramMessengerNotificationSenderInterface
{
    public function __construct(
        private readonly TelegramMessengerClientInterface $client,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function send(
        string $text,
        int $chatId,
        array $buttonRows = [],
        string $failureLogMessage = 'Telegram notification send failed',
        array $logContext = [],
    ): bool {
        try {
            $this->client->sendMessage($chatId, $text, $buttonRows);

            return true;
        } catch (TelegramMessengerException $exception) {
            $this->logger->warning($failureLogMessage, [
                ...$logContext,
                'chat_id' => $chatId,
                'error' => $this->redactBotToken($exception->userMessage()),
            ]);

            return false;
        } catch (Throwable $exception) {
            $this->logger->warning($failureLogMessage, [
                ...$logContext,
                'chat_id' => $chatId,
                'error' => $this->redactBotToken($exception->getMessage()),
            ]);

            return false;
        }
    }

    /**
     * Убирает токен бота из URL в тексте ошибки cURL.
     */
    private function redactBotToken(string $message): string
    {
        $redacted = preg_replace('#/bot[^/\s]+#', '/bot[redacted]', $message);

        return is_string($redacted) ? $redacted : $message;
    }
}
