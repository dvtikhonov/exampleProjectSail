<?php

declare(strict_types=1);

namespace App\Enums\Food;

/**
 * Единица измерения веса или объёма блюда.
 */
enum DishWeightUnit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Milliliter = 'ml';
    case Liter = 'l';

    /**
     * Краткое обозначение для UI (г, кг, мл, л).
     */
    public function label(): string
    {
        return match ($this) {
            self::Gram => 'г',
            self::Kilogram => 'кг',
            self::Milliliter => 'мл',
            self::Liter => 'л',
        };
    }
}
