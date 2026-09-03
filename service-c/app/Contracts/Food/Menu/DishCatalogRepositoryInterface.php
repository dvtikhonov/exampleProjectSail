<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\DishRecord;

/**
 * Репозиторий блюд для каталога и пользовательских сценариев (корзина, отдача изображений).
 */
interface DishCatalogRepositoryInterface
{
    /**
     * Ищет блюдо по id, включая soft-deleted (для истории заказов и отдачи изображений).
     */
    public function findByIdWithTrashed(int $id): ?DishRecord;

    /**
     * Доступное блюдо с категорией меню и рестораном для добавления в корзину.
     */
    public function findAvailableWithRestaurant(int $id): ?DishRecord;

    /**
     * Пакетная загрузка блюд с категорией меню и рестораном (ключ — id блюда).
     *
     * @param  list<int>  $ids
     * @return array<int, DishRecord>
     */
    public function findAvailableWithRestaurantByIds(array $ids): array;

    /**
     * Регистронезависимый поиск блюд по точному совпадению названия (с категорией и рестораном).
     *
     * @return list<DishRecord>
     */
    public function findByNameCaseInsensitive(string $name, ?int $restaurantId = null): array;
}
