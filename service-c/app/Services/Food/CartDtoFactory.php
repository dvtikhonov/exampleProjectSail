<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUrlResolverInterface;
use App\DTO\Food\CartDto;
use App\DTO\Food\CartItemDto;
use App\Enums\Food\DishWeightUnit;
use App\Models\Cart;
use App\Models\MaxUser;

/**
 * Сборка CartDto из модели корзины с расчётом сумм.
 */
class CartDtoFactory
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
        private readonly CartTotalsCalculator $cartTotalsCalculator,
    ) {}

    /**
     * Преобразует модель корзины в DTO с актуальными суммами.
     */
    public function fromModel(Cart $cart, MaxUser $maxUser): CartDto
    {
        $cart->loadMissing(['restaurant', 'items.dish', 'items.comboPartnerDish']);

        $items = [];
        $itemsTotal = 0.0;

        foreach ($cart->items as $item) {
            $unitPrice = (float) $item->dish->price;
            $lineTotal = $unitPrice * $item->quantity;
            $itemsTotal += $lineTotal;

            $weightUnit = $item->dish->weight_unit ?? DishWeightUnit::Gram;

            $items[] = new CartItemDto(
                id: $item->id,
                dishId: $item->dish_id,
                dishName: $item->dish->name,
                unitPrice: $this->moneyFormatter->format($unitPrice),
                quantity: $item->quantity,
                lineTotal: $this->moneyFormatter->format($lineTotal),
                imageUrl: $this->imageUrlResolver->resolvePublicUrl($item->dish_id, $item->dish->image_url),
                weight: $this->formatWeight($item->dish->weight),
                weightUnit: $weightUnit->value,
                weightUnitLabel: $weightUnit->label(),
                comboRef: $item->combo_ref,
                comboPartnerDishId: $item->combo_partner_dish_id,
                comboPartnerDishName: $item->comboPartnerDish?->name,
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
            nextTierMinTotal: $totals->nextTierMinTotal !== null
                ? $this->moneyFormatter->format($totals->nextTierMinTotal)
                : null,
            nextTierDeliveryCost: $totals->nextTierDeliveryCost !== null
                ? $this->moneyFormatter->format($totals->nextTierDeliveryCost)
                : null,
            amountToNextTier: $totals->amountToNextTier !== null
                ? $this->moneyFormatter->format($totals->amountToNextTier)
                : null,
        );
    }

    private function formatWeight(mixed $weight): string
    {
        return (string) (int) round((float) $weight);
    }
}
