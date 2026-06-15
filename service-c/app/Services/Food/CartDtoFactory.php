<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUrlResolverInterface;
use App\DTO\Food\CartDto;
use App\DTO\Food\CartItemDto;
use App\Models\Cart;
use App\Models\MaxUser;

class CartDtoFactory
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
        private readonly CartTotalsCalculator $cartTotalsCalculator,
    ) {}

    public function fromModel(Cart $cart, MaxUser $maxUser): CartDto
    {
        $cart->loadMissing(['restaurant', 'items.dish']);

        $items = [];
        $itemsTotal = 0.0;

        foreach ($cart->items as $item) {
            $unitPrice = (float) $item->dish->price;
            $lineTotal = $unitPrice * $item->quantity;
            $itemsTotal += $lineTotal;

            $items[] = new CartItemDto(
                id: $item->id,
                dishId: $item->dish_id,
                dishName: $item->dish->name,
                unitPrice: $this->moneyFormatter->format($unitPrice),
                quantity: $item->quantity,
                lineTotal: $this->moneyFormatter->format($lineTotal),
                imageUrl: $this->imageUrlResolver->resolvePublicUrl($item->dish_id, $item->dish->image_url),
            );
        }

        $totals = $this->cartTotalsCalculator->calculate(
            restaurantId: $cart->restaurant_id,
            maxUser: $maxUser,
            itemsTotal: $itemsTotal,
        );

        return new CartDto(
            id: $cart->id,
            restaurantId: $cart->restaurant_id,
            restaurantName: $cart->restaurant->name,
            status: $cart->status->value,
            items: $items,
            itemsTotal: $this->moneyFormatter->format($totals->itemsTotal),
            deliveryCost: $totals->deliveryCost !== null
                ? $this->moneyFormatter->format($totals->deliveryCost)
                : null,
            total: $this->moneyFormatter->format($totals->total),
            deliveryAddress: $cart->delivery_address,
            customerCategory: $totals->customerCategory,
            deliveryApplicable: $totals->deliveryApplicable,
        );
    }
}
