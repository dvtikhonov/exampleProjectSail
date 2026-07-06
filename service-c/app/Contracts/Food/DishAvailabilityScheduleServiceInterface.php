<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\DishAvailabilityGridDto;
use App\DTO\Food\DishAvailabilityUpdateDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Сервис графика доступности блюд по датам.
 */
interface DishAvailabilityScheduleServiceInterface
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

    /**
     * @throws FoodDomainException
     */
    public function syncSchedule(DishAvailabilityUpdateDto $dto): void;
}
