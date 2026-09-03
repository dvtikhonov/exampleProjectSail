<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use Shared\MaxMessenger\DTO\MaxInlineKeyboardButtonDto;

/**
 * Единая отправка MAX-уведомлений с логированием ошибок.
 */
interface MaxMessengerNotificationSenderInterface
{
    /**
     * Отправляет сообщение одному получателю (чат или пользователь).
     *
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     * @param  array<string, mixed>  $logContext
     */
    public function send(
        string $text,
        ?int $chatId = null,
        ?int $userId = null,
        array $buttonRows = [],
        string $failureLogMessage = 'MAX notification send failed',
        array $logContext = [],
    ): bool;

    /**
     * Рассылает сообщение всем получателям UI Stand (chatIds + userIds из resolver).
     *
     * @param  array<int, array<int, MaxInlineKeyboardButtonDto>>  $buttonRows
     * @param  array<string, mixed>  $logContext
     */
    public function broadcastToUiStand(
        string $text,
        array $buttonRows,
        array $logContext,
        string $failureLogMessage,
    ): void;
}
