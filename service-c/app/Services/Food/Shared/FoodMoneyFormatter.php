<?php

declare(strict_types=1);

namespace App\Services\Food\Shared;

use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;

/**
 * Форматирование денежных сумм для API заказа еды.
 */
class FoodMoneyFormatter implements FoodMoneyFormatterInterface
{
    /**
     * Форматирует сумму с двумя знаками после запятой.
     */
    public function format(string|float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
