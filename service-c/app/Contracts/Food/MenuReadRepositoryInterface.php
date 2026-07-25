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
     *
     * @param  bool  $includeUnavailable  true — включать блюда с is_available=false (ручной заказ)
     */
    public function findActiveWithMenu(int $restaurantId, bool $includeUnavailable = false): ?Restaurant;
}
