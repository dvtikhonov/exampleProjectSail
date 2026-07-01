<?php

declare(strict_types=1);

namespace App\DTO\UrlShortener;

/**
 * Данные для создания короткой ссылки.
 */
readonly class CreateShortLinkDto
{
    public function __construct(
        /** Владелец короткой ссылки (Filament / web guard). */
        public int $userId,
        /** Целевой URL, на который ведёт редирект. */
        public string $originalUrl,
    ) {}
}
