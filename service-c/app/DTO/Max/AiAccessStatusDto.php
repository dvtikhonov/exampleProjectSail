<?php

declare(strict_types=1);

namespace App\DTO\Max;

/**
 * Статус доступа AI к базе для MAX mini-app.
 */
readonly class AiAccessStatusDto
{
    public function __construct(
        public bool $enabled,
        public ?int $activeMaxUserId,
        public ?string $expiresAt,
    ) {}
}

