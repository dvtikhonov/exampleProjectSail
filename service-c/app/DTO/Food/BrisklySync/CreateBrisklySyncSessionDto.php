<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Вход создания сессии (токен не попадает в Record/БД).
 */
readonly class CreateBrisklySyncSessionDto
{
    public function __construct(
        public int $restaurantId,
        public string $brisklyToken,
        public ?int $vpsCategoryId,
        public ?string $searchText,
        public ?string $clarification,
        public ?int $createdByMaxUserId,
    ) {}
}
