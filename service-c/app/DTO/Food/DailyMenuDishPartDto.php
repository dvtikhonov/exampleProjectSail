<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Часть позиции ежедневного меню (одно блюдо или половина комбо).
 */
readonly class DailyMenuDishPartDto
{
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $weightLabel,
        public float $price,
    ) {}
}
