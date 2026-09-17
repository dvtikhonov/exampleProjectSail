<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\DishAdminRepositoryInterface;
use App\DTO\Food\Menu\CreateDishDto;
use App\DTO\Food\Menu\DishAdminListResultDto;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Menu\UpdateDishDto;
use App\Enums\Food\Cart\CartStatus;
use App\Models\Food\Dish;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-реализация административного репозитория блюд (CRUD / bulk).
 */
class EloquentDishAdminRepository implements DishAdminRepositoryInterface
{
    /**
     * Жёсткий лимит списка блюд для админ-API (защита от полной выгрузки).
     */
    private const int ADMIN_LIST_LIMIT = 500;

    public function __construct(
        private readonly DishMapper $dishMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?DishRecord
    {
        $dish = Dish::query()
            ->with(['menuCategory.restaurant'])
            ->find($id);

        return $dish !== null ? $this->dishMapper->toRecord($dish) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findByNameAndMenuCategoryId(string $name, int $menuCategoryId): ?DishRecord
    {
        $dish = Dish::query()
            ->where('menu_category_id', $menuCategoryId)
            ->where('name', $name)
            ->first();

        return $dish !== null ? $this->dishMapper->toRecord($dish) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findByNamesAndMenuCategoryId(array $names, int $menuCategoryId): array
    {
        if ($names === []) {
            return [];
        }

        $uniqueNames = array_values(array_unique($names));

        $dishes = Dish::query()
            ->where('menu_category_id', $menuCategoryId)
            ->whereIn('name', $uniqueNames)
            ->orderBy('id')
            ->get();

        /** @var array<string, DishRecord> $keyed */
        $keyed = [];

        foreach ($dishes as $dish) {
            $name = (string) $dish->name;

            if (! array_key_exists($name, $keyed)) {
                $keyed[$name] = $this->dishMapper->toRecord($dish);
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
    public function createMany(array $dtos): array
    {
        $created = [];

        foreach ($dtos as $dto) {
            $created[] = $this->dishMapper->toRecord(
                Dish::query()->create($this->dishMapper->toCreateAttributes($dto)),
            );
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
    public function listForAdmin(
        ?int $restaurantId,
        ?int $categoryId,
        ?string $nameSearch = null,
        ?bool $isAvailable = null,
    ): DishAdminListResultDto {
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

        $total = (clone $query)->count();

        $items = $query
            ->limit(self::ADMIN_LIST_LIMIT)
            ->get()
            ->map(fn (Dish $dish): DishRecord => $this->dishMapper->toRecord($dish))
            ->values()
            ->all();

        return new DishAdminListResultDto(
            items: $items,
            total: $total,
            truncated: $total > count($items),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function create(CreateDishDto $dto): DishRecord
    {
        $dish = Dish::query()->create($this->dishMapper->toCreateAttributes($dto));

        return $this->dishMapper->toRecord($dish->load(['menuCategory.restaurant']));
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $dishId, UpdateDishDto $dto): DishRecord
    {
        $dish = Dish::query()->findOrFail($dishId);
        $dish->update($this->dishMapper->toUpdateAttributes($dto));

        $fresh = $dish->fresh(['menuCategory.restaurant']) ?? $dish->load(['menuCategory.restaurant']);

        return $this->dishMapper->toRecord($fresh);
    }

    /**
     * {@inheritDoc}
     */
    public function updateImageUrl(int $dishId, string $imageUrl): DishRecord
    {
        $dish = Dish::query()->findOrFail($dishId);
        $dish->update(['image_url' => $imageUrl]);

        $fresh = $dish->fresh(['menuCategory.restaurant']) ?? $dish->load(['menuCategory.restaurant']);

        return $this->dishMapper->toRecord($fresh);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $dishId): void
    {
        Dish::query()->whereKey($dishId)->delete();
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
