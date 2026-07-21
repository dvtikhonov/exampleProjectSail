<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\Dish;

/**
 * Каталог доступных блюд для ежедневного уведомления о меню.
 */
interface DailyMenuCatalogRepositoryInterface
{
    /**
     * Доступные блюда активных ресторанов с категорией (для сборки меню дня).
     *
     * @return list<Dish>
     */
    public function listAvailableWithCategories(): array;
}
