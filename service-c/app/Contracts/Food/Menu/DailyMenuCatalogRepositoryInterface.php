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
}
