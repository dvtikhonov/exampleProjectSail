<?php

declare(strict_types=1);

namespace App\Support\Max\Food\Formatting;

/**
 * Форматирование денежных сумм для MAX-уведомлений о заказе.
 */
final class FoodOrderMaxMoneyFormatter
{
    /**
     * Форматирует денежную сумму для клиентского уведомления.
     */
    public function formatMoneyAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Форматирует цену в рублях без копеек для уведомления менеджеру.
     */
    public function formatRublesAmount(mixed $amount): string
    {
        return (string) (int) round((float) ($amount ?? 0));
    }
}
