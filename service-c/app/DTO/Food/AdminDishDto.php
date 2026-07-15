<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Полные данные блюда для административного списка и формы.
 */
readonly class AdminDishDto
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $menuCategoryId,
        public string $menuCategoryName,
        public int $restaurantId,
        public string $restaurantName,
        public string $weight,
        public string $weightUnit,
        public string $weightUnitLabel,
        public string $price,
        public ?int $vatRate,
        public string $vatRateLabel,
        public bool $isAvailable,
        public ?string $imageUrl,
    ) {}

    /**
     * Преобразует админский DTO блюда в массив.
     *
     * @return array<string, int|string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'menu_category_id' => $this->menuCategoryId,
            'menu_category_name' => $this->menuCategoryName,
            'restaurant_id' => $this->restaurantId,
            'restaurant_name' => $this->restaurantName,
            'weight' => $this->weight,
            'weight_unit' => $this->weightUnit,
            'weight_unit_label' => $this->weightUnitLabel,
            'price' => $this->price,
            'vat_rate' => $this->vatRate,
            'vat_rate_label' => $this->vatRateLabel,
            'is_available' => $this->isAvailable,
            'image_url' => $this->imageUrl,
        ];
    }
}
