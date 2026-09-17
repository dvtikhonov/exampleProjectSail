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

    /**
     * Переводит денежную сумму в целое число копеек без float-суммирования строк.
     */
    public function toCents(string|float|int $amount): int;

    /**
     * Форматирует копейки в денежную строку с двумя знаками.
     */
    public function formatCents(int $cents): string;
}
