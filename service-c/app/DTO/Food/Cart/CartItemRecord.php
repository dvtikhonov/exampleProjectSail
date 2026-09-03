<?php

declare(strict_types=1);

namespace App\DTO\Food\Cart;

use App\DTO\Food\Menu\DishRecord;
use App\Enums\Food\Cart\CartStatus;

/**
 * Доменная проекция позиции корзины без Eloquent.
 */
readonly class CartItemRecord
{
    public function __construct(
        public int $id,
        public int $cartId,
        public int $dishId,
        public int $quantity,
        public ?string $comboRef = null,
        public ?int $comboPartnerDishId = null,
        public ?DishRecord $dish = null,
        public ?string $comboPartnerDishName = null,
        public ?int $cartMaxUserId = null,
        public ?int $cartCreatedByMaxUserId = null,
        public ?CartStatus $cartStatus = null,
        public ?int $cartRestaurantId = null,
    ) {}
}
