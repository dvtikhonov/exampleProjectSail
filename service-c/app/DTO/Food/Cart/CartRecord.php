<?php

declare(strict_types=1);

namespace App\DTO\Food\Cart;

use App\Enums\Food\Cart\CartStatus;

/**
 * Доменная проекция корзины без Eloquent.
 */
readonly class CartRecord
{
    /**
     * @param  list<CartItemRecord>  $items
     */
    public function __construct(
        public int $id,
        public int $maxUserId,
        public ?int $createdByMaxUserId,
        public int $restaurantId,
        public CartStatus $status,
        public ?string $deliveryAddress,
        public ?string $restaurantName = null,
        public array $items = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
