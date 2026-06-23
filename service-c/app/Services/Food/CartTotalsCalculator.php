<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DeliveryTierRepositoryInterface;
use App\DTO\Food\CartTotalsDto;
use App\DTO\Food\CustomerCategoryDto;
use App\Models\MaxUser;

/**
 * Расчёт итогов корзины с учётом тарифов доставки.
 */
class CartTotalsCalculator
{
    public function __construct(
        private readonly DeliveryCostResolver $deliveryCostResolver,
        private readonly DeliveryTierRepositoryInterface $deliveryTierRepository,
    ) {}

    /**
     * Рассчитывает суммы блюд, доставки и итог корзины.
     */
    public function calculate(int $restaurantId, MaxUser $maxUser, float $itemsTotal): CartTotalsDto
    {
        if (! $this->deliveryCostResolver->isApplicable($maxUser)) {
            return new CartTotalsDto(
                itemsTotal: $itemsTotal,
                deliveryCost: null,
                total: $itemsTotal,
                deliveryApplicable: false,
                customerCategory: null,
            );
        }

        $maxUser->loadMissing('customerCategory');

        $categoryDto = new CustomerCategoryDto(
            id: $maxUser->customerCategory->id,
            name: $maxUser->customerCategory->name,
        );

        $tiers = $this->deliveryTierRepository->findTiersFor(
            $restaurantId,
            $maxUser->customer_category_id,
        );

        $deliveryCost = $this->deliveryCostResolver->resolve($itemsTotal, $tiers);

        return new CartTotalsDto(
            itemsTotal: $itemsTotal,
            deliveryCost: $deliveryCost,
            total: $itemsTotal + $deliveryCost,
            deliveryApplicable: true,
            customerCategory: $categoryDto,
        );
    }
}
