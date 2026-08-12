<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\MenuDto;
use App\DTO\Food\Shared\RestaurantSummaryDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Запросы меню и списка активных ресторанов.
 */
interface MenuQueryServiceInterface
{
    /**
     * Возвращает список активных ресторанов.
     *
     * @return list<RestaurantSummaryDto>
     */
    public function listActiveRestaurants(): array;

    /**
     * Возвращает меню ресторана с категориями и блюдами.
     *
     * @param  bool  $includeUnavailable  true — включать недоступные блюда (ручной заказ)
     *
     * @throws FoodDomainException
     */
    public function getRestaurantMenu(int $restaurantId, bool $includeUnavailable = false): MenuDto;
}
