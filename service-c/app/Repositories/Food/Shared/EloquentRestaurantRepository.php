<?php

declare(strict_types=1);

namespace App\Repositories\Food\Shared;

use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\DTO\Food\Menu\RestaurantSummaryRecord;
use App\Models\Food\Restaurant;
use App\Repositories\Food\Menu\DishMapper;

/**
 * Eloquent-реализация репозитория ресторанов.
 */
class EloquentRestaurantRepository implements RestaurantRepositoryInterface
{
    public function __construct(
        private readonly DishMapper $dishMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findAllActive(): array
    {
        return Restaurant::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Restaurant $restaurant): RestaurantSummaryRecord => $this->dishMapper->toRestaurantSummary($restaurant))
            ->values()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveById(int $restaurantId): ?RestaurantSummaryRecord
    {
        $restaurant = Restaurant::query()
            ->where('is_active', true)
            ->find($restaurantId);

        return $restaurant !== null ? $this->dishMapper->toRestaurantSummary($restaurant) : null;
    }
}
