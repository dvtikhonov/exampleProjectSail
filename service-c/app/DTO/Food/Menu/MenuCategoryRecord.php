<?php

declare(strict_types=1);

namespace App\DTO\Food\Menu;

/**
 * Доменная проекция категории меню без Eloquent.
 */
readonly class MenuCategoryRecord
{
    /**
     * @param  list<MenuCategoryAvailabilityOffsetDto>  $availabilityOffsets
     * @param  list<DishRecord>  $dishes
     */
    public function __construct(
        public int $id,
        public int $restaurantId,
        public string $name,
        public int $sortOrder,
        public bool $isComboAvailable,
        public ?RestaurantSummaryRecord $restaurant = null,
        public array $availabilityOffsets = [],
        public array $dishes = [],
    ) {}
}
