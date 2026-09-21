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

        return bcadd($this->normalizeAmount($amount), '0', 2);
    }

    /**
     * Форматирует цену в рублях без копеек для уведомления менеджеру.
     */
    public function formatRublesAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0';
        }

        $normalized = $this->normalizeAmount($amount);
        // Округление half-away-from-zero: ±0.5, затем усечение scale=0.
        $half = bccomp($normalized, '0', 14) < 0 ? '-0.5' : '0.5';

        return bcadd($normalized, $half, 0);
    }

    /**
     * Приводит сумму к числовой строке для bcmath без (float)-каста.
     */
    private function normalizeAmount(mixed $amount): string
    {
        if (is_int($amount)) {
            return (string) $amount;
        }

        if (is_float($amount)) {
            return sprintf('%.14F', $amount);
        }

        $normalized = trim((string) $amount);

        return $normalized === '' ? '0' : $normalized;
    }
}
