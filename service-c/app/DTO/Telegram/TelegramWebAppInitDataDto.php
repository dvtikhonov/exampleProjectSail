<?php

declare(strict_types=1);

namespace App\DTO\Telegram;

/**
 * Распарсенные и проверенные данные initData Telegram WebApp.
 */
readonly class TelegramWebAppInitDataDto
{
    public function __construct(
        public int $telegramUserId,
        public string $firstName,
        public ?string $lastName,
        public ?string $username,
        public ?string $languageCode,
        public ?string $photoUrl,
        public int $authDate,
        public ?string $queryId = null,
    ) {}
}
