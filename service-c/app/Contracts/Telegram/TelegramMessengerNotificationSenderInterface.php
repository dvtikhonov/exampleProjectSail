<?php

declare(strict_types=1);

namespace App\Contracts\Telegram;

use App\DTO\Telegram\TelegramInlineKeyboardButtonDto;

/**
 * Единая отправка Telegram-уведомлений с логированием ошибок.
 */
interface TelegramMessengerNotificationSenderInterface
{
    /**
     * Отправляет сообщение в Telegram-чат (DM: chat_id = external_user_id).
     *
     * @param  array<int, array<int, TelegramInlineKeyboardButtonDto>>  $buttonRows
     * @param  array<string, mixed>  $logContext
     */
    public function send(
        string $text,
        int $chatId,
        array $buttonRows = [],
        string $failureLogMessage = 'Telegram notification send failed',
        array $logContext = [],
    ): bool;
}
