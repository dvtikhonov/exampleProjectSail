<?php

declare(strict_types=1);

namespace App\Contracts\Telegram;

use App\DTO\Telegram\TelegramInlineKeyboardButtonDto;
use App\Exceptions\Telegram\TelegramMessengerException;

/**
 * Низкоуровневый клиент Telegram Bot API (sendMessage).
 */
interface TelegramMessengerClientInterface
{
    /**
     * Отправляет текстовое сообщение в чат (для DM chat_id = telegram user id).
     *
     * @param  array<int, array<int, TelegramInlineKeyboardButtonDto>>  $buttonRows
     *
     * @throws TelegramMessengerException
     */
    public function sendMessage(int $chatId, string $text, array $buttonRows = []): void;
}
