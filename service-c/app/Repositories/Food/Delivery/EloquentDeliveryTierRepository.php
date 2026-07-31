<?php

declare(strict_types=1);

namespace App\Repositories\Food\Delivery;

use App\Contracts\Food\Delivery\DeliveryTierRepositoryInterface;
use App\DTO\Food\Delivery\DeliveryTierDto;
use App\Models\Food\RestaurantCategoryDeliveryTier;

/**
 * Eloquent-реализация репозитория тарифов доставки.
 */
class EloquentDeliveryTierRepository implements DeliveryTierRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findTiersFor(int $restaurantId, int $customerCategoryId): array
    {
        return RestaurantCategoryDeliveryTier::query()
            ->where('restaurant_id', $restaurantId)
            ->where('customer_category_id', $customerCategoryId)
            ->orderByDesc('min_items_total')
            ->get()
            ->map(static fn (RestaurantCategoryDeliveryTier $tier): DeliveryTierDto => new DeliveryTierDto(
                minItemsTotal: (float) $tier->min_items_total,
                deliveryCost: (float) $tier->delivery_cost,
            ))
            ->all();
    }
}
