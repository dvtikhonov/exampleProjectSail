<?php

declare(strict_types=1);

namespace App\Repositories\Food;

use App\Contracts\Food\DishRepositoryInterface;
use App\Enums\Food\CartStatus;
use App\Models\Dish;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Eloquent-реализация репозитория блюд.
 */
class EloquentDishRepository implements DishRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?Dish
    {
        return Dish::query()
            ->with(['menuCategory.restaurant'])
            ->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByNameAndMenuCategoryId(string $name, int $menuCategoryId): ?Dish
    {
        return Dish::query()
            ->where('menu_category_id', $menuCategoryId)
            ->where('name', $name)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForAdmin(
        ?int $restaurantId,
        ?int $categoryId,
        ?string $nameSearch = null,
        int $perPage = 50,
    ): LengthAwarePaginator {
        $query = Dish::query()
            ->with(['menuCategory.restaurant'])
            ->orderBy('name');

        if ($restaurantId !== null) {
            $query->whereHas(
                'menuCategory',
                static fn ($categoryQuery) => $categoryQuery->where('restaurant_id', $restaurantId),
            );
        }

        if ($categoryId !== null) {
            $query->where('menu_category_id', $categoryId);
        }

        if ($nameSearch !== null && $nameSearch !== '') {
            $query->whereLike('name', '%'.$nameSearch.'%');
        }

        return $query->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $attributes): Dish
    {
        return Dish::query()->create($attributes);
    }

    /**
     * {@inheritDoc}
     */
    public function update(Dish $dish, array $attributes): Dish
    {
        $dish->update($attributes);

        return $dish->refresh(['menuCategory.restaurant']);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Dish $dish): void
    {
        $dish->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function existsInDraftCarts(int $dishId): bool
    {
        return Dish::query()
            ->whereKey($dishId)
            ->whereHas(
                'cartItems.cart',
                static fn ($cartQuery) => $cartQuery->where('status', CartStatus::Draft->value),
            )
            ->exists();
    }
}
