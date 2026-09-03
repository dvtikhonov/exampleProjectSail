<?php

declare(strict_types=1);

namespace App\Repositories\Food\Shared;

use App\Contracts\Food\Shared\MenuReadRepositoryInterface;
use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\DTO\Food\Menu\RestaurantSummaryRecord;
use App\DTO\Food\Menu\RestaurantWithMenuRecord;
use App\Models\Food\Restaurant;
use App\Repositories\Food\Menu\DishMapper;

/**
 * Eloquent-реализация репозитория ресторанов и чтения меню.
 */
class EloquentRestaurantRepository implements MenuReadRepositoryInterface, RestaurantRepositoryInterface
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

    /**
     * {@inheritDoc}
     */
    public function findActiveWithMenu(int $restaurantId, bool $includeUnavailable = false): ?RestaurantWithMenuRecord
    {
        $restaurant = Restaurant::query()
            ->where('is_active', true)
            ->with([
                'menuCategories.dishes' => static function ($query) use ($includeUnavailable): void {
                    if (! $includeUnavailable) {
                        $query->where('is_available', true);
                    }

                    $query->orderBy('name');
                },
            ])
            ->find($restaurantId);

        return $restaurant !== null ? $this->dishMapper->toRestaurantWithMenu($restaurant) : null;
    }
}
