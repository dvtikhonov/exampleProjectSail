<?php

declare(strict_types=1);

namespace App\Services\Food;

/**
 * Форматирование денежных сумм для API заказа еды.
 */
class FoodMoneyFormatter
{
    /**
     * Форматирует сумму с двумя знаками после запятой.
     */
    public function format(string|float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
