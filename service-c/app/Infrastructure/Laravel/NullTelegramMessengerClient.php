<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Telegram\TelegramMessengerClientInterface;

/**
 * No-op клиент Telegram, когда bot token не задан.
 */
final class NullTelegramMessengerClient implements TelegramMessengerClientInterface
{
    /**
     * {@inheritDoc}
     */
    public function sendMessage(int $chatId, string $text, array $buttonRows = []): void
    {
        // Intentionally empty: локальная среда / тесты без TELEGRAM_BOT_TOKEN.
    }
}
