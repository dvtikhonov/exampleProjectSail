<?php

declare(strict_types=1);

namespace App\DTO\Food\Menu;

/**
 * Данные для создания категории меню.
 */
readonly class CreateMenuCategoryDto
{
    /**
     * @param  list<MenuCategoryAvailabilityOffsetDto>  $availabilityOffsets
     */
    public function __construct(
        public int $restaurantId,
        public string $name,
        public int $sortOrder,
        public bool $isComboAvailable,
        public array $availabilityOffsets = [],
    ) {}
}
