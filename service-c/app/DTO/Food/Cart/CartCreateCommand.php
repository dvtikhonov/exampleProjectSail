<?php

declare(strict_types=1);

namespace App\DTO\Food\Cart;

use App\Enums\Food\Cart\CartStatus;

/**
 * Команда создания черновика корзины.
 */
readonly class CartCreateCommand
{
    public function __construct(
        public int $maxUserId,
        public ?int $createdByMaxUserId,
        public int $restaurantId,
        public CartStatus $status,
        public ?string $deliveryAddress,
    ) {}
}
