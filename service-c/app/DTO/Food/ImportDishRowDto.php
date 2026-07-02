<?php

declare(strict_types=1);

namespace App\DTO\Food;

use App\Enums\Food\DishVatRate;
use App\Enums\Food\DishWeightUnit;

/**
 * Распарсенная строка импорта блюда из таблицы.
 */
readonly class ImportDishRowDto
{
    public function __construct(
        public string $name,
        public string $weight,
        public DishWeightUnit $weightUnit,
        public string $price,
        public DishVatRate $vatRate,
        public bool $isAvailable,
        public ?string $description,
    ) {}
}
