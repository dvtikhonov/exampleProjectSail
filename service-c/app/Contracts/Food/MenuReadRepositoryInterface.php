<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\Restaurant;

/**
 * Чтение меню ресторана для клиентского API MAX mini-app.
 */
interface MenuReadRepositoryInterface
{
    /**
     * Активный ресторан с категориями меню и блюдами или null, если не найден.
     */
    public function findActiveWithMenu(int $restaurantId): ?Restaurant;
}
