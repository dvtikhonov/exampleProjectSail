<?php

declare(strict_types=1);

namespace App\Services\Food\Cart;

use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\Contracts\Food\Delivery\DeliveryTierRepositoryInterface;
use App\DTO\Food\Cart\CartTotalsDto;
use App\Services\Food\Delivery\DeliveryCostResolver;

/**
 * Расчёт итогов корзины с учётом тарифов доставки.
 */
class CartTotalsCalculator
{
    public function __construct(
        private readonly DeliveryCostResolver $deliveryCostResolver,
        private readonly DeliveryTierRepositoryInterface $deliveryTierRepository,
        private readonly CustomerCategoryRepositoryInterface $customerCategoryRepository,
    ) {}

    /**
     * Рассчитывает суммы блюд, доставки и итог корзины.
     */
    public function calculate(int $restaurantId, int $maxUserId, float $itemsTotal): CartTotalsDto
    {
        $category = $this->customerCategoryRepository->findCategoryForMaxUserId($maxUserId);

        if ($category === null || ! $this->deliveryCostResolver->isApplicable($category->id)) {
            return new CartTotalsDto(
                itemsTotal: $itemsTotal,
                deliveryCost: null,
                total: $itemsTotal,
                deliveryApplicable: false,
                customerCategory: null,
            );
        }

        $tiers = $this->deliveryTierRepository->findTiersFor(
            $restaurantId,
            $category->id,
        );

        $deliveryCost = $this->deliveryCostResolver->resolve($itemsTotal, $tiers);
        $nextTier = $this->deliveryCostResolver->resolveNextTier($itemsTotal, $tiers);
        $nextTierMinTotal = null;
        $nextTierDeliveryCost = null;
        $amountToNextTier = null;

        if ($nextTier !== null) {
            $amountToNextTier = $nextTier->minItemsTotal - $itemsTotal;

            if ($amountToNextTier > 0) {
                $nextTierMinTotal = $nextTier->minItemsTotal;
                $nextTierDeliveryCost = $nextTier->deliveryCost;
            } else {
                $amountToNextTier = null;
            }
        }

        return new CartTotalsDto(
            itemsTotal: $itemsTotal,
            deliveryCost: $deliveryCost,
            total: $itemsTotal + $deliveryCost,
            deliveryApplicable: true,
            customerCategory: $category,
            nextTierMinTotal: $nextTierMinTotal,
            nextTierDeliveryCost: $nextTierDeliveryCost,
            amountToNextTier: $amountToNextTier,
        );
    }
}
