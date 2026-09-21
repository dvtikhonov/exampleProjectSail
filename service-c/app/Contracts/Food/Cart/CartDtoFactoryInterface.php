<?php

declare(strict_types=1);

namespace App\Contracts\Food\Cart;

use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\Cart\CartRecord;

/**
 * Сборка CartDto из доменной проекции корзины с расчётом сумм.
 */
interface CartDtoFactoryInterface
{
    /**
     * Преобразует проекцию корзины в DTO с актуальными суммами.
     */
    public function fromRecord(CartRecord $cart, int $maxUserId): CartDto;
}
