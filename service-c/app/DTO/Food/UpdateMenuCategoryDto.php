<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Данные для обновления категории меню.
 */
readonly class UpdateMenuCategoryDto
{
    public function __construct(
        public int $restaurantId,
        public string $name,
        public int $sortOrder,
        public bool $isComboAvailable,
    ) {}
}
