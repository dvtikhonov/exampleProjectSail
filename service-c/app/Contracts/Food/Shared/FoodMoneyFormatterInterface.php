<?php

declare(strict_types=1);

namespace App\Contracts\Food\Shared;

/**
 * Форматирование денежных сумм для API заказа еды.
 */
interface FoodMoneyFormatterInterface
{
    /**
     * Форматирует сумму с двумя знаками после запятой.
     */
    public function format(string|float|int $amount): string;
}
