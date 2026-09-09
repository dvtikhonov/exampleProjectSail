<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\DTO\Food\Menu\DishRecord;
use App\Models\Food\Dish;

/**
 * Eloquent-реализация каталожного репозитория блюд (корзина, изображения, photo-text).
 */
class EloquentDishCatalogRepository implements DishCatalogRepositoryInterface
{
    public function __construct(
        private readonly DishMapper $dishMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findByIdWithTrashed(int $id): ?DishRecord
    {
        $dish = Dish::query()
            ->withTrashed()
            ->find($id);

        return $dish !== null ? $this->dishMapper->toRecord($dish) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findAvailableWithRestaurant(int $id): ?DishRecord
    {
        $dish = Dish::query()
            ->with('menuCategory.restaurant')
            ->find($id);

        return $dish !== null ? $this->dishMapper->toRecord($dish) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findAvailableWithRestaurantByIds(array $ids): array
    {
        $uniqueIds = array_values(array_unique(array_map(static fn (int $id): int => $id, $ids)));

        if ($uniqueIds === []) {
            return [];
        }

        $result = [];

        foreach (
            Dish::query()
                ->with('menuCategory.restaurant')
                ->whereIn('id', $uniqueIds)
                ->get() as $dish
        ) {
            $result[(int) $dish->id] = $this->dishMapper->toRecord($dish);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function findByNameCaseInsensitive(string $name, ?int $restaurantId = null): array
    {
        $normalized = trim($name);

        if ($normalized === '') {
            return [];
        }

        $lowerName = mb_strtolower($normalized, 'UTF-8');

        $query = Dish::query()
            ->with('menuCategory.restaurant')
            ->whereRaw('LOWER(name) = ?', [$lowerName]);

        if ($restaurantId !== null) {
            $query->whereHas(
                'menuCategory',
                static fn ($categoryQuery) => $categoryQuery->where('restaurant_id', $restaurantId),
            );
        }

        return $query
            ->orderBy('id')
            ->get()
            ->map(fn (Dish $dish): DishRecord => $this->dishMapper->toRecord($dish))
            ->all();
    }
}
