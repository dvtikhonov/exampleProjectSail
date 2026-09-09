<?php

declare(strict_types=1);

namespace App\Support\Max\Food\Formatting;

use App\Enums\Food\Menu\DishWeightUnit;

/**
 * Форматирование веса позиций для MAX-уведомлений о заказе.
 */
final class FoodOrderMaxWeightFormatter
{
    /**
     * Форматирует вес позиции: «110г».
     *
     * @param  array<string, mixed>  $item
     */
    public function formatWeightLabel(array $item): ?string
    {
        $weight = $item['weight'] ?? null;

        if ($weight === null || $weight === '') {
            return null;
        }

        $unitValue = (string) ($item['weight_unit'] ?? DishWeightUnit::Gram->value);
        $unit = DishWeightUnit::tryFrom($unitValue) ?? DishWeightUnit::Gram;

        return sprintf('%s%s', (string) (int) round((float) $weight), $unit->label());
    }
}
