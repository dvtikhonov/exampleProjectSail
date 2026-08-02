<?php

declare(strict_types=1);

namespace App\DTO\Food\Menu;

/**
 * Данные для обновления категории меню.
 */
readonly class UpdateMenuCategoryDto
{
    /**
     * @param  list<MenuCategoryAvailabilityOffsetDto>|null  $availabilityOffsets
     *                                                         null — не менять правила смещения
     */
    public function __construct(
        public int $restaurantId,
        public string $name,
        public int $sortOrder,
        public bool $isComboAvailable,
        public ?array $availabilityOffsets = null,
    ) {}
}
