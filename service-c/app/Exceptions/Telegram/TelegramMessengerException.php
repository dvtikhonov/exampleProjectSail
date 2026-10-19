<?php

declare(strict_types=1);

namespace App\Exceptions\Telegram;

use RuntimeException;

/**
 * Базовое исключение клиента Telegram Bot API.
 */
class TelegramMessengerException extends RuntimeException
{
    /**
     * Сообщение, безопасное для логов (без токена).
     */
    public function userMessage(): string
    {
        return $this->getMessage();
    }
}
