<?php

declare(strict_types=1);

namespace App\DTO\Food\Menu;

use App\Enums\Food\Menu\DishWeightUnit;

/**
 * Доменная проекция блюда без Eloquent.
 */
readonly class DishRecord
{
    public function __construct(
        public int $id,
        public int $menuCategoryId,
        public string $name,
        public ?string $description,
        public string $weight,
        public DishWeightUnit $weightUnit,
        public ?string $imageUrl,
        public string $price,
        public ?int $vatRate,
        public bool $isAvailable,
        public ?MenuCategoryRecord $menuCategory = null,
    ) {}
}
