<?php

declare(strict_types=1);

namespace App\Contracts\Telegram;

/**
 * Обработка Telegram-команды /start: регистрация (если нужно) и приветственный экран.
 */
interface TelegramBotStartServiceInterface
{
    /**
     * Регистрирует пользователя Telegram при отсутствии в max_users и отправляет экран с Mini App.
     */
    public function handleStart(
        int $telegramUserId,
        int $chatId,
        string $firstName,
        ?string $lastName,
        ?string $username,
        ?string $languageCode,
    ): void;
}
