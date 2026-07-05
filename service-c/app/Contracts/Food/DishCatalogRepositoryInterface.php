<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\Dish;

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
}
