<?php

declare(strict_types=1);

namespace App\Repositories\Food;

use App\Contracts\Food\DishAvailabilityRepositoryInterface;
use App\Models\Dish;
use App\Models\DishAvailabilityDate;
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
    public function syncDishAvailabilityInRange(
        int $dishId,
        array $availableDates,
        string $rangeFrom,
        string $rangeTo,
        string $editableFrom,
    ): void {
        $syncFrom = max($rangeFrom, $editableFrom);

        $datesInScope = array_values(array_filter(
            $availableDates,
            static fn (string $date): bool => $date >= $syncFrom && $date <= $rangeTo,
        ));

        DB::transaction(function () use ($dishId, $datesInScope, $syncFrom, $rangeTo): void {
            DishAvailabilityDate::query()
                ->where('dish_id', $dishId)
                ->whereBetween('available_date', [$syncFrom, $rangeTo])
                ->when(
                    $datesInScope !== [],
                    static fn ($query) => $query->whereNotIn('available_date', $datesInScope),
                )
                ->delete();

            if ($datesInScope === []) {
                return;
            }

            $existingDates = DishAvailabilityDate::query()
                ->where('dish_id', $dishId)
                ->whereIn('available_date', $datesInScope)
                ->pluck('available_date')
                ->map(static fn ($date) => $date->format('Y-m-d'))
                ->all();

            foreach (array_diff($datesInScope, $existingDates) as $date) {
                DishAvailabilityDate::query()->create([
                    'dish_id' => $dishId,
                    'available_date' => $date,
                ]);
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
}
