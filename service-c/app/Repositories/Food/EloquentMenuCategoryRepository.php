<?php

declare(strict_types=1);

namespace App\Repositories\Food;

use App\Contracts\Food\MenuCategoryRepositoryInterface;
use App\Models\MenuCategory;

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
            ->with('restaurant')
            ->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function listForAdmin(?int $restaurantId = null): array
    {
        $query = MenuCategory::query()
            ->with('restaurant')
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
}
