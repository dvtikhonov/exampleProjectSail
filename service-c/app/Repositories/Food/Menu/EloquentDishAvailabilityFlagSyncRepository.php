<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\DishAvailabilityFlagSyncRepositoryInterface;
use App\Models\Food\Dish;
use App\Models\Food\DishAvailabilityDate;

/**
 * Eloquent-реализация порта синхронизации флага is_available.
 */
class EloquentDishAvailabilityFlagSyncRepository implements DishAvailabilityFlagSyncRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function clearAllDishesIsAvailable(): int
    {
        return Dish::query()
            ->where('is_available', true)
            ->update(['is_available' => false]);
    }

    /**
     * {@inheritDoc}
     */
    public function syncDishesIsAvailableForDate(string $date): int
    {
        $dishIdsWithAvailability = DishAvailabilityDate::query()
            ->whereDate('available_date', $date)
            ->distinct()
            ->pluck('dish_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $updated = 0;

        $updated += Dish::query()
            ->when(
                $dishIdsWithAvailability !== [],
                static fn ($query) => $query->whereIn('id', $dishIdsWithAvailability),
                static fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('is_available', false)
            ->update(['is_available' => true]);

        $updated += Dish::query()
            ->when(
                $dishIdsWithAvailability !== [],
                static fn ($query) => $query->whereNotIn('id', $dishIdsWithAvailability),
            )
            ->where('is_available', true)
            ->update(['is_available' => false]);

        return $updated;
    }

    /**
     * {@inheritDoc}
     */
    public function syncDishesIsAvailableForCategoryAndDate(int $menuCategoryId, string $date): int
    {
        $dishIdsWithAvailability = DishAvailabilityDate::query()
            ->whereDate('available_date', $date)
            ->whereHas(
                'dish',
                static fn ($query) => $query->where('menu_category_id', $menuCategoryId),
            )
            ->distinct()
            ->pluck('dish_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $updated = 0;

        $updated += Dish::query()
            ->where('menu_category_id', $menuCategoryId)
            ->when(
                $dishIdsWithAvailability !== [],
                static fn ($query) => $query->whereIn('id', $dishIdsWithAvailability),
                static fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('is_available', false)
            ->update(['is_available' => true]);

        $updated += Dish::query()
            ->where('menu_category_id', $menuCategoryId)
            ->when(
                $dishIdsWithAvailability !== [],
                static fn ($query) => $query->whereNotIn('id', $dishIdsWithAvailability),
            )
            ->where('is_available', true)
            ->update(['is_available' => false]);

        return $updated;
    }

    /**
     * {@inheritDoc}
     */
    public function enableDishesIsAvailableForCategoryDates(array $categoryIdToDate): int
    {
        if ($categoryIdToDate === []) {
            return 0;
        }

        $dishIds = DishAvailabilityDate::query()
            ->where(function ($query) use ($categoryIdToDate): void {
                foreach ($categoryIdToDate as $menuCategoryId => $date) {
                    $query->orWhere(function ($inner) use ($menuCategoryId, $date): void {
                        $inner->where('available_date', $date)
                            ->whereHas(
                                'dish',
                                static fn ($dishQuery) => $dishQuery->where(
                                    'menu_category_id',
                                    (int) $menuCategoryId,
                                ),
                            );
                    });
                }
            })
            ->distinct()
            ->pluck('dish_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($dishIds === []) {
            return 0;
        }

        return Dish::query()
            ->whereIn('id', $dishIds)
            ->where('is_available', false)
            ->update(['is_available' => true]);
    }
}
