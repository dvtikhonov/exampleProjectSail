<?php

declare(strict_types=1);

namespace App\Services\Food\BrisklySync;

use App\Enums\Food\Menu\DishWeightUnit;

/**
 * Имя позиции для CREATE в Briskly: «наименование, вес» (компактный вес как в MAX-меню).
 */
final class BrisklyCreateNameFormatter
{
    /**
     * Single: «Тостер Тест, 200г»; без веса — только наименование.
     */
    public static function forSingle(string $name, string $weight, DishWeightUnit $unit): string
    {
        $trimmedName = trim($name);
        $weightLabel = self::weightLabel($weight, $unit);

        if ($weightLabel === null) {
            return $trimmedName;
        }

        return $trimmedName.', '.$weightLabel;
    }

    /**
     * Combo: «Суп, 300г / Салат, 150г» (вес у каждой части независимо).
     */
    public static function forCombo(
        string $firstName,
        string $firstWeight,
        DishWeightUnit $firstUnit,
        string $secondName,
        string $secondWeight,
        DishWeightUnit $secondUnit,
    ): string {
        return self::forSingle($firstName, $firstWeight, $firstUnit)
            .' / '
            .self::forSingle($secondName, $secondWeight, $secondUnit);
    }

    /**
     * Компактный лейбл веса: «200г», «1л». Пустой вес → null.
     */
    public static function weightLabel(string $weight, DishWeightUnit $unit): ?string
    {
        if (trim($weight) === '') {
            return null;
        }

        return sprintf('%s%s', (string) (int) round((float) $weight), $unit->label());
    }
}
