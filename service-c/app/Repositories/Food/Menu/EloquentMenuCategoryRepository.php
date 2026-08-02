<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\MenuCategoryRepositoryInterface;
use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;
use App\Models\Food\MenuCategoryAvailabilityOffset;
use Illuminate\Support\Str;

/**
 * Eloquent-реализация репозитория категорий меню.
 */
class EloquentMenuCategoryRepository implements MenuCategoryRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?MenuCategory
    {
        return MenuCategory::query()
            ->with(['restaurant', 'availabilityOffsets'])
            ->find($id);
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

        return $query->get()->all();
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $attributes): MenuCategory
    {
        return MenuCategory::query()->create($attributes);
    }

    /**
     * {@inheritDoc}
     */
    public function update(MenuCategory $category, array $attributes): MenuCategory
    {
        $category->update($attributes);

        return $category->fresh(['restaurant', 'availabilityOffsets']) ?? $category;
    }

    /**
     * {@inheritDoc}
     */
    public function syncAvailabilityOffsets(MenuCategory $category, array $offsets): void
    {
        $category->availabilityOffsets()->delete();

        if ($offsets === []) {
            $category->unsetRelation('availabilityOffsets');

            return;
        }

        $now = now();
        $rows = [];

        foreach ($offsets as $offset) {
            $groupKey = (string) Str::uuid();

            foreach ($offset->weekdays as $weekday) {
                $rows[] = [
                    'menu_category_id' => $category->id,
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

        $category->unsetRelation('availabilityOffsets');
    }

    /**
     * {@inheritDoc}
     */
    public function delete(MenuCategory $category): void
    {
        $category->delete();
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
