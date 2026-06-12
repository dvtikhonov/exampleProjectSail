<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\DTO\Food\CartDto;
use App\DTO\Food\CartItemDto;
use App\Models\Cart;

class CartDtoFactory
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
    ) {}

    public function fromModel(Cart $cart): CartDto
    {
        $cart->loadMissing(['restaurant', 'items.dish']);

        $items = [];
        $total = 0.0;

        foreach ($cart->items as $item) {
            $unitPrice = (float) $item->dish->price;
            $lineTotal = $unitPrice * $item->quantity;
            $total += $lineTotal;

            $items[] = new CartItemDto(
                id: $item->id,
                dishId: $item->dish_id,
                dishName: $item->dish->name,
                unitPrice: $this->moneyFormatter->format($unitPrice),
                quantity: $item->quantity,
                lineTotal: $this->moneyFormatter->format($lineTotal),
            );
        }

        return new CartDto(
            id: $cart->id,
            restaurantId: $cart->restaurant_id,
            restaurantName: $cart->restaurant->name,
            status: $cart->status->value,
            items: $items,
            total: $this->moneyFormatter->format($total),
        );
    }
}
