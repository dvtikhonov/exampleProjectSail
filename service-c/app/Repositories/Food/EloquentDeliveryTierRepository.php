<?php

declare(strict_types=1);

namespace App\Repositories\Food;

use App\Contracts\Food\DeliveryTierRepositoryInterface;
use App\DTO\Food\DeliveryTierDto;
use App\Models\RestaurantCategoryDeliveryTier;

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
