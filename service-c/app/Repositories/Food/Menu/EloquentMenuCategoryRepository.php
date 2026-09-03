<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\MenuCategoryRepositoryInterface;
use App\DTO\Food\Menu\MenuCategoryRecord;
use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;
use App\Models\Food\MenuCategoryAvailabilityOffset;
use Illuminate\Support\Str;

/**
 * Eloquent-реализация репозитория категорий меню.
 */
class EloquentMenuCategoryRepository implements MenuCategoryRepositoryInterface
{
    public function __construct(
        private readonly DishMapper $dishMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?MenuCategoryRecord
    {
        $category = MenuCategory::query()
            ->with(['restaurant', 'availabilityOffsets'])
            ->find($id);

        return $category !== null ? $this->dishMapper->toCategoryRecord($category) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function listForAdmin(?int $restaurantId = null): array
    {
        $query = MenuCategory::query()
            ->with(['restaurant', 'availabilityOffsets'])
            ->join('max_restaurants', 'max_menu_categories.restaurant_id', '=', 'max_restaurants.id')
            ->orderBy('max_restaurants.name')
            ->orderBy('max_menu_categories.sort_order')
            ->orderBy('max_menu_categories.name')
            ->select('max_menu_categories.*');

        if ($restaurantId !== null) {
            $query->where('max_menu_categories.restaurant_id', $restaurantId);
        }

        return $query
            ->get()
            ->map(fn (MenuCategory $category): MenuCategoryRecord => $this->dishMapper->toCategoryRecord($category))
            ->values()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $attributes): MenuCategoryRecord
    {
        $category = MenuCategory::query()->create($attributes);

        return $this->dishMapper->toCategoryRecord(
            $category->load(['restaurant', 'availabilityOffsets']),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $categoryId, array $attributes): MenuCategoryRecord
    {
        $category = MenuCategory::query()->findOrFail($categoryId);
        $category->update($attributes);

        $fresh = $category->fresh(['restaurant', 'availabilityOffsets']) ?? $category->load(['restaurant', 'availabilityOffsets']);

        return $this->dishMapper->toCategoryRecord($fresh);
    }

    /**
     * {@inheritDoc}
     */
    public function syncAvailabilityOffsets(int $categoryId, array $offsets): void
    {
        MenuCategoryAvailabilityOffset::query()
            ->where('menu_category_id', $categoryId)
            ->delete();

        if ($offsets === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($offsets as $offset) {
            $groupKey = (string) Str::uuid();

            foreach ($offset->weekdays as $weekday) {
                $rows[] = [
                    'menu_category_id' => $categoryId,
                    'group_key' => $groupKey,
                    'weekday' => $weekday,
                    'offset_days' => $offset->offsetDays,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            MenuCategoryAvailabilityOffset::query()->insert($rows);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $categoryId): void
    {
        MenuCategory::query()->whereKey($categoryId)->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function countDishes(int $categoryId): int
    {
        return Dish::query()
            ->where('menu_category_id', $categoryId)
            ->count();
    }

    /**
     * {@inheritDoc}
     */
    public function nextSortOrderForRestaurant(int $restaurantId): int
    {
        $maxSortOrder = MenuCategory::query()
            ->where('restaurant_id', $restaurantId)
            ->max('sort_order');

        return ((int) $maxSortOrder) + 1;
    }
}
