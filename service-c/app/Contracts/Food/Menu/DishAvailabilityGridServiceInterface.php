<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\DishAvailabilityGridDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Чтение сетки графика доступности блюд по датам.
 */
interface DishAvailabilityGridServiceInterface
{
    /**
     * @throws FoodDomainException
     */
    public function getGrid(
        int $restaurantId,
        int $categoryId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): DishAvailabilityGridDto;
}
