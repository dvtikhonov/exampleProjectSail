<?php

declare(strict_types=1);

namespace App\Repositories\Food;

use App\Contracts\Food\MenuReadRepositoryInterface;
use App\Contracts\Food\RestaurantRepositoryInterface;
use App\Models\Restaurant;

/**
 * Eloquent-реализация репозитория ресторанов и чтения меню.
 */
class EloquentRestaurantRepository implements MenuReadRepositoryInterface, RestaurantRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findAllActive(): array
    {
        return Restaurant::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveById(int $restaurantId): ?Restaurant
    {
        return Restaurant::query()
            ->where('is_active', true)
            ->find($restaurantId);
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveWithMenu(int $restaurantId): ?Restaurant
    {
        return Restaurant::query()
            ->where('is_active', true)
            ->with([
                'menuCategories.dishes' => static fn ($query) => $query
                    ->where('is_available', true)
                    ->orderBy('name'),
            ])
            ->find($restaurantId);
    }
}
