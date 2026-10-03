<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\DailyMenuCatalogRepositoryInterface;
use App\DTO\Food\Menu\DishRecord;
use App\Models\Food\Dish;

/**
 * Eloquent-каталог доступных блюд для уведомления о меню дня.
 */
class EloquentDailyMenuCatalogRepository implements DailyMenuCatalogRepositoryInterface
{
    public function __construct(
        private readonly DishMapper $dishMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listAvailableWithCategories(): array
    {
        return $this->mapAvailableDishes(
            Dish::query()
                ->with(['menuCategory'])
                ->where('is_available', true)
                ->whereHas('menuCategory.restaurant', static function ($query): void {
                    $query->where('is_active', true);
                })
                ->whereHas('menuCategory', static function ($query): void {
                    $query->whereNull('deleted_at');
                })
                ->orderBy('id')
                ->get(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function listAvailableWithCategoriesForRestaurant(int $restaurantId): array
    {
        // Briskly sync: без фильтра is_available — сравниваем цены и вне меню дня.
        return $this->mapAvailableDishes(
            Dish::query()
                ->with(['menuCategory'])
                ->whereNull('deleted_at')
                ->whereHas('menuCategory.restaurant', static function ($query) use ($restaurantId): void {
                    $query->where('is_active', true)
                        ->where('id', $restaurantId);
                })
                ->whereHas('menuCategory', static function ($query) use ($restaurantId): void {
                    $query->whereNull('deleted_at')
                        ->where('restaurant_id', $restaurantId);
                })
                ->orderBy('id')
                ->get(),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Dish>  $dishes
     * @return list<DishRecord>
     */
    private function mapAvailableDishes($dishes): array
    {
        return $dishes
            ->sortBy([
                fn (Dish $dish): int => (int) ($dish->menuCategory?->restaurant_id ?? 0),
                fn (Dish $dish): int => (int) ($dish->menuCategory?->sort_order ?? 0),
                fn (Dish $dish): int => (int) ($dish->menuCategory?->id ?? 0),
                fn (Dish $dish): int => (int) $dish->id,
            ])
            ->values()
            ->map(fn (Dish $dish): DishRecord => $this->dishMapper->toRecord($dish))
            ->all();
    }
}
