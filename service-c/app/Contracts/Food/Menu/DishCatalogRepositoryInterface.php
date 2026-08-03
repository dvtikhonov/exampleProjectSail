<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\Models\Food\Dish;
use Illuminate\Support\Collection;

/**
 * Репозиторий блюд для каталога и пользовательских сценариев (корзина, отдача изображений).
 */
interface DishCatalogRepositoryInterface
{
    /**
     * Ищет блюдо по id, включая soft-deleted (для истории заказов и отдачи изображений).
     */
    public function findByIdWithTrashed(int $id): ?Dish;

    /**
     * Доступное блюдо с категорией меню и рестораном для добавления в корзину.
     */
    public function findAvailableWithRestaurant(int $id): ?Dish;

    /**
     * Пакетная загрузка блюд с категорией меню и рестораном (ключ — id блюда).
     *
     * @param  list<int>  $ids
     * @return Collection<int, Dish>
     */
    public function findAvailableWithRestaurantByIds(array $ids): Collection;
}
