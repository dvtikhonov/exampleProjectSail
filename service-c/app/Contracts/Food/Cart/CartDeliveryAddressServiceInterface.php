<?php

declare(strict_types=1);

namespace App\Contracts\Food\Cart;

use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\Shared\MaxUserIdentity;

/**
 * Обновление адреса доставки в черновике корзины.
 */
interface CartDeliveryAddressServiceInterface
{
    /**
     * Сохраняет адрес доставки в профиле пользователя и в черновике корзины (если есть).
     *
     * Без корзины адрес всё равно сохраняется в профиле MAX — чтобы показывать его в меню.
     */
    public function update(MaxUserIdentity $maxUser, string $deliveryAddress): ?CartDto;
}
