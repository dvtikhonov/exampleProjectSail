<?php

declare(strict_types=1);

namespace App\Repositories\Food;

use App\Contracts\Food\DailyMenuCatalogRepositoryInterface;
use App\Models\Dish;

/**
 * Eloquent-каталог доступных блюд для уведомления о меню дня.
 */
class EloquentDailyMenuCatalogRepository implements DailyMenuCatalogRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function listAvailableWithCategories(): array
    {
        return Dish::query()
            ->with(['menuCategory'])
            ->where('is_available', true)
            ->whereHas('menuCategory.restaurant', static function ($query): void {
                $query->where('is_active', true);
            })
            ->whereHas('menuCategory', static function ($query): void {
                $query->whereNull('deleted_at');
            })
            ->orderBy('id')
            ->get()
            ->sortBy([
                fn (Dish $dish): int => (int) ($dish->menuCategory?->restaurant_id ?? 0),
                fn (Dish $dish): int => (int) ($dish->menuCategory?->sort_order ?? 0),
                fn (Dish $dish): int => (int) ($dish->menuCategory?->id ?? 0),
                fn (Dish $dish): int => (int) $dish->id,
            ])
            ->values()
            ->all();
    }
}
