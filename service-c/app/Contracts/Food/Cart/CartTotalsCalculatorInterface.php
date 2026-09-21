<?php

declare(strict_types=1);

namespace App\Contracts\Food\Cart;

use App\DTO\Food\Cart\CartTotalsDto;

/**
 * Расчёт итогов корзины с учётом тарифов доставки.
 */
interface CartTotalsCalculatorInterface
{
    /**
     * Рассчитывает суммы блюд, доставки и итог корзины.
     */
    public function calculate(int $restaurantId, int $maxUserId, float $itemsTotal): CartTotalsDto;
}
