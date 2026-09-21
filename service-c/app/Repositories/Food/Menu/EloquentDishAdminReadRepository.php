<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\DishAdminReadRepositoryInterface;
use App\DTO\Food\Menu\DishAdminListResultDto;
use App\DTO\Food\Menu\DishRecord;
use App\Enums\Food\Cart\CartStatus;
use App\Models\Food\Dish;

/**
 * Eloquent-реализация read-порта административного репозитория блюд.
 */
class EloquentDishAdminReadRepository implements DishAdminReadRepositoryInterface
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
