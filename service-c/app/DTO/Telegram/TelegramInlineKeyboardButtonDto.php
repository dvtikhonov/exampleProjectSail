<?php

declare(strict_types=1);

namespace App\DTO\Telegram;

/**
 * Кнопка inline-клавиатуры Telegram Bot API (web_app или url).
 *
 * Ровно одно из {@see $webAppUrl} / {@see $url} должно быть задано.
 */
readonly class TelegramInlineKeyboardButtonDto
{
    public function __construct(
        public string $text,
        public ?string $webAppUrl = null,
        public ?string $url = null,
    ) {}
}
