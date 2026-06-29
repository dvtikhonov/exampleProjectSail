<?php

declare(strict_types=1);

namespace App\DTO\Food;

use App\Enums\Food\DishVatRate;
use App\Enums\Food\DishWeightUnit;

/**
 * Входные данные для создания блюда (без загружаемого файла).
 */
readonly class CreateDishDto
{
    public function __construct(
        public string $name,
        public int $menuCategoryId,
        public ?string $description,
        public string $weight,
        public DishWeightUnit $weightUnit,
        public string $price,
        public DishVatRate $vatRate,
        public bool $isAvailable,
    ) {}
}
