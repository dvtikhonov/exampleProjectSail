<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\DishAvailabilityRepositoryInterface;
use App\Models\Food\Dish;
use App\Models\Food\DishAvailabilityDate;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-реализация репозитория графика доступности блюд.
 */
class EloquentDishAvailabilityRepository implements DishAvailabilityRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function listDishesForCategory(int $restaurantId, int $categoryId): array
    {
        return Dish::query()
            ->where('menu_category_id', $categoryId)
            ->whereHas(
                'menuCategory',
                static fn ($query) => $query->where('restaurant_id', $restaurantId),
            )
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function getScheduleForDishes(array $dishIds, string $dateFrom, string $dateTo): array
    {
        if ($dishIds === []) {
            return [];
        }

        $rows = DishAvailabilityDate::query()
            ->whereIn('dish_id', $dishIds)
            ->whereBetween('available_date', [$dateFrom, $dateTo])
            ->orderBy('available_date')
            ->get(['dish_id', 'available_date']);

        $schedule = [];

        foreach ($dishIds as $dishId) {
            $schedule[$dishId] = [];
        }

        foreach ($rows as $row) {
            $schedule[(int) $row->dish_id][] = $row->available_date->format('Y-m-d');
        }

        return $schedule;
    }

    /**
     * {@inheritDoc}
     */
    public function syncDishesAvailabilityInRange(
        array $dishAvailableDates,
        string $rangeFrom,
        string $rangeTo,
        string $editableFrom,
    ): void {
        if ($dishAvailableDates === []) {
            return;
        }

        $syncFrom = max($rangeFrom, $editableFrom);
        $dishIds = array_map(static fn (int|string $id): int => (int) $id, array_keys($dishAvailableDates));
        $now = now();
        $rows = [];

        foreach ($dishAvailableDates as $dishId => $availableDates) {
            $datesInScope = array_values(array_unique(array_filter(
                $availableDates,
                static fn (string $date): bool => $date >= $syncFrom && $date <= $rangeTo,
            )));

            foreach ($datesInScope as $date) {
                $rows[] = [
                    'dish_id' => (int) $dishId,
                    'available_date' => $date,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($dishIds, $syncFrom, $rangeTo, $rows): void {
            DishAvailabilityDate::query()
                ->whereIn('dish_id', $dishIds)
                ->whereBetween('available_date', [$syncFrom, $rangeTo])
                ->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                DishAvailabilityDate::query()->insert($chunk);
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function dishesBelongToCategory(array $dishIds, int $categoryId, int $restaurantId): bool
    {
        if ($dishIds === []) {
            return true;
        }

        $matchedCount = Dish::query()
            ->whereIn('id', $dishIds)
            ->where('menu_category_id', $categoryId)
            ->whereHas(
                'menuCategory',
                static fn ($query) => $query->where('restaurant_id', $restaurantId),
            )
            ->count();

        return $matchedCount === count(array_unique($dishIds));
    }

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
