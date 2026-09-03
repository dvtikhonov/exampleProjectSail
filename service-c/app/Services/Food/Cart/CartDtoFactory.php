<?php

declare(strict_types=1);

namespace App\Services\Food\Cart;

use App\Contracts\Food\Menu\DishImageUrlResolverInterface;
use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\DTO\Food\Cart\CartDto;
use App\DTO\Food\Cart\CartItemDto;
use App\DTO\Food\Cart\CartRecord;
use App\Enums\Food\Menu\DishWeightUnit;
use App\Services\Food\Shared\FoodMoneyFormatter;

/**
 * Сборка CartDto из доменной проекции корзины с расчётом сумм.
 */
class CartDtoFactory
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
        private readonly CartTotalsCalculator $cartTotalsCalculator,
        private readonly MaxUserDeliveryAddressInterface $maxUserDeliveryAddressService,
        private readonly MenuAvailabilityDateResolverInterface $menuAvailabilityDateResolver,
    ) {}

    /**
     * Преобразует проекцию корзины в DTO с актуальными суммами.
     */
    public function fromRecord(CartRecord $cart, int $maxUserId): CartDto
    {
        $items = [];
        $itemsTotal = 0.0;

        foreach ($cart->items as $item) {
            $dish = $item->dish ?? throw new \LogicException(
                'Для CartDtoFactory требуется загруженное блюдо в CartItemRecord.',
            );

            $unitPrice = (float) $dish->price;
            $lineTotal = $unitPrice * $item->quantity;
            $itemsTotal += $lineTotal;

            $weightUnit = $dish->weightUnit ?? DishWeightUnit::Gram;

            $items[] = new CartItemDto(
                id: $item->id,
                dishId: $item->dishId,
                dishName: $dish->name,
                unitPrice: $this->moneyFormatter->format($unitPrice),
                quantity: $item->quantity,
                lineTotal: $this->moneyFormatter->format($lineTotal),
                imageUrl: $this->imageUrlResolver->resolvePublicUrl($dish->id, $dish->imageUrl),
                weight: $this->formatWeight($dish->weight),
                weightUnit: $weightUnit->value,
                weightUnitLabel: $weightUnit->label(),
                comboRef: $item->comboRef,
                comboPartnerDishId: $item->comboPartnerDishId,
                comboPartnerDishName: $item->comboPartnerDishName,
            );
        }

        $totals = $this->cartTotalsCalculator->calculate(
            restaurantId: $cart->restaurantId,
            maxUserId: $maxUserId,
            itemsTotal: $itemsTotal,
        );

        return new CartDto(
            id: $cart->id,
            restaurantId: $cart->restaurantId,
            restaurantName: (string) ($cart->restaurantName ?? ''),
            status: $cart->status->value,
            items: $items,
            itemsTotal: $this->moneyFormatter->format($totals->itemsTotal),
            deliveryCost: $totals->deliveryCost !== null
                ? $this->moneyFormatter->format($totals->deliveryCost)
                : null,
            total: $this->moneyFormatter->format($totals->total),
            deliveryAddress: $this->resolveDeliveryAddress($cart, $maxUserId),
            deliveryDate: $this->menuAvailabilityDateResolver->resolve()->date,
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

    /**
     * Адрес из корзины или сохранённый в профиле пользователя.
     */
    private function resolveDeliveryAddress(CartRecord $cart, int $maxUserId): ?string
    {
        $fromCart = $cart->deliveryAddress;

        if ($fromCart !== null && trim($fromCart) !== '') {
            return trim($fromCart);
        }

        return $this->maxUserDeliveryAddressService->defaultForMaxUserId($maxUserId);
    }

    /**
     * Форматирует вес блюда для отображения в DTO.
     */
    private function formatWeight(mixed $weight): string
    {
        return (string) (int) round((float) $weight);
    }
}
