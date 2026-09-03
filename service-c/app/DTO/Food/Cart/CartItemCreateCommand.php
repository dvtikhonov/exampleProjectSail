<?php

declare(strict_types=1);

namespace App\DTO\Food\Cart;

/**
 * Команда создания позиции корзины.
 */
readonly class CartItemCreateCommand
{
    public function __construct(
        public int $cartId,
        public int $dishId,
        public int $quantity,
        public ?string $comboRef = null,
        public ?int $comboPartnerDishId = null,
    ) {}
}
