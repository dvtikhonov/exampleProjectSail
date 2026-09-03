<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\Contracts\Max\MaxLoadTestDataRepositoryInterface;
use App\Models\Food\Cart;
use App\Models\Food\Dish;
use App\Models\Food\FoodOrder;
use App\Models\Food\Restaurant;

/**
 * Eloquent data-access для нагрузочного стенда MAX.
 */
final class EloquentMaxLoadTestDataRepository implements MaxLoadTestDataRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function listActiveRestaurantIds(): array
    {
        return Restaurant::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function enableUnavailableDishesForRestaurants(array $restaurantIds): int
    {
        if ($restaurantIds === []) {
            return 0;
        }

        return Dish::query()
            ->where('is_available', false)
            ->whereHas(
                'menuCategory',
                static fn ($query) => $query->whereIn('restaurant_id', $restaurantIds),
            )
            ->update(['is_available' => true]);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteOrdersForMaxUserIds(array $maxUserIds): int
    {
        if ($maxUserIds === []) {
            return 0;
        }

        return FoodOrder::query()->whereIn('max_user_id', $maxUserIds)->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function deleteCartsForMaxUserIds(array $maxUserIds): int
    {
        if ($maxUserIds === []) {
            return 0;
        }

        return Cart::query()->whereIn('max_user_id', $maxUserIds)->delete();
    }
}
