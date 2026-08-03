<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\DishAdminRepositoryInterface;
use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\Enums\Food\Cart\CartStatus;
use App\Models\Food\Dish;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-реализация репозитория блюд.
 */
class EloquentDishRepository implements DishAdminRepositoryInterface, DishCatalogRepositoryInterface
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
    public function findByIdWithTrashed(int $id): ?Dish
    {
        return Dish::query()
            ->withTrashed()
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
    public function findByNamesAndMenuCategoryId(array $names, int $menuCategoryId): Collection
    {
        if ($names === []) {
            return collect();
        }

        $uniqueNames = array_values(array_unique($names));

        $dishes = Dish::query()
            ->where('menu_category_id', $menuCategoryId)
            ->whereIn('name', $uniqueNames)
            ->orderBy('id')
            ->get();

        $keyed = collect();

        foreach ($dishes as $dish) {
            $name = (string) $dish->name;

            if (! $keyed->has($name)) {
                $keyed->put($name, $dish);
            }
        }

        return $keyed;
    }

    /**
     * {@inheritDoc}
     */
    public function updatePricesByIds(array $pricesById): void
    {
        if ($pricesById === []) {
            return;
        }

        $cases = [];
        $bindings = [];

        foreach ($pricesById as $id => $price) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = (int) $id;
            $bindings[] = $price;
        }

        $ids = array_map(static fn ($id): int => (int) $id, array_keys($pricesById));
        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $caseSql = implode(' ', $cases);

        DB::update(
            "UPDATE max_dishes SET price = CASE id {$caseSql} END, updated_at = ? WHERE id IN ({$idPlaceholders}) AND deleted_at IS NULL",
            [...$bindings, now(), ...$ids],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function createMany(array $rows): Collection
    {
        $created = collect();

        foreach ($rows as $attributes) {
            $created->push(Dish::query()->create($attributes));
        }

        return $created;
    }

    /**
     * {@inheritDoc}
     */
    public function updateImageUrlsByIds(array $imageUrlsById): void
    {
        if ($imageUrlsById === []) {
            return;
        }

        $cases = [];
        $bindings = [];

        foreach ($imageUrlsById as $id => $imageUrl) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = (int) $id;
            $bindings[] = $imageUrl;
        }

        $ids = array_map(static fn ($id): int => (int) $id, array_keys($imageUrlsById));
        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $caseSql = implode(' ', $cases);

        DB::update(
            "UPDATE max_dishes SET image_url = CASE id {$caseSql} END, updated_at = ? WHERE id IN ({$idPlaceholders}) AND deleted_at IS NULL",
            [...$bindings, now(), ...$ids],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForAdmin(
        ?int $restaurantId,
        ?int $categoryId,
        ?string $nameSearch = null,
        ?bool $isAvailable = null,
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

        if ($isAvailable !== null) {
            $query->where('is_available', $isAvailable);
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

    /**
     * {@inheritDoc}
     */
    public function findAvailableWithRestaurant(int $id): ?Dish
    {
        return Dish::query()
            ->with('menuCategory.restaurant')
            ->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findAvailableWithRestaurantByIds(array $ids): Collection
    {
        $uniqueIds = array_values(array_unique(array_map(static fn (int $id): int => $id, $ids)));

        if ($uniqueIds === []) {
            return collect();
        }

        return Dish::query()
            ->with('menuCategory.restaurant')
            ->whereIn('id', $uniqueIds)
            ->get()
            ->keyBy(static fn (Dish $dish): int => (int) $dish->id);
    }
}
