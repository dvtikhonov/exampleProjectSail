<?php

declare(strict_types=1);

namespace App\Repositories\Food\Shared;

use App\Contracts\Food\Shared\MenuReadRepositoryInterface;
use App\DTO\Food\Menu\RestaurantWithMenuRecord;
use App\Models\Food\Restaurant;
use App\Repositories\Food\Menu\DishMapper;

/**
 * Eloquent-реализация чтения меню активного ресторана.
 */
class EloquentMenuReadRepository implements MenuReadRepositoryInterface
{
    public function __construct(
        private readonly DishMapper $dishMapper,
    ) {}

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
