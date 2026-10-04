<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Вход создания сессии (Bearer захватывается на сервере, не из request).
 */
readonly class CreateBrisklySyncSessionDto
{
    public function __construct(
        public int $restaurantId,
        public ?int $vpsCategoryId,
        public ?string $searchText,
        public ?string $clarification,
        public ?int $createdByMaxUserId,
    ) {}
}
