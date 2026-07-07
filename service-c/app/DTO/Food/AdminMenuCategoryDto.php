<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Полные данные категории меню для административного списка и формы.
 */
readonly class AdminMenuCategoryDto
{
    public function __construct(
        public int $id,
        public string $name,
        public int $restaurantId,
        public string $restaurantName,
        public int $sortOrder,
        public bool $isComboAvailable,
        public int $dishesCount,
    ) {}

    /**
     * @return array<string, int|string|bool>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'restaurant_id' => $this->restaurantId,
            'restaurant_name' => $this->restaurantName,
            'sort_order' => $this->sortOrder,
            'is_combo_available' => $this->isComboAvailable,
            'dishes_count' => $this->dishesCount,
        ];
    }
}
