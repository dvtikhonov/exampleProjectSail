<?php

declare(strict_types=1);

namespace App\DTO\Food;

use App\Enums\Food\DishVatRate;
use App\Enums\Food\DishWeightUnit;

/**
 * Строка импорта блюда из XLS/XLSX.
 */
readonly class ImportDishRowDto
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $weight,
        public DishWeightUnit $weightUnit,
        public string $price,
        public DishVatRate $vatRate,
        public bool $isAvailable,
    ) {}
}
