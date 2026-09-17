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

    /**
     * {@inheritDoc}
     */
    public function toCents(string|float|int $amount): int
    {
        $normalized = $this->format($amount);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$rubles, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $cents = ((int) $rubles) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    /**
     * {@inheritDoc}
     */
    public function formatCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
