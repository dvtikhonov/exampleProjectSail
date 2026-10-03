<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\DishRecord;

/**
 * Каталог доступных блюд для ежедневного уведомления о меню.
 */
interface DailyMenuCatalogRepositoryInterface
{
    /**
     * Доступные блюда активных ресторанов с категорией (для сборки меню дня).
     *
     * @return list<DishRecord>
     */
    public function listAvailableWithCategories(): array;

    /**
     * Блюда одного ресторана с категорией для Briskly sync source.
     * Включает недоступные (is_available=false): цена в VPS нужна для сравнения с Briskly
     * даже вне меню дня. Soft-deleted и чужие рестораны — нет.
     *
     * @return list<DishRecord>
     */
    public function listAvailableWithCategoriesForRestaurant(int $restaurantId): array;
}
