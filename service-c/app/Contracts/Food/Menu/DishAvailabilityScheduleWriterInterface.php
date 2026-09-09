<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\DishAvailabilityUpdateDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Запись (синхронизация) графика доступности блюд по датам.
 */
interface DishAvailabilityScheduleWriterInterface
{
    /**
     * @throws FoodDomainException
     */
    public function syncSchedule(DishAvailabilityUpdateDto $dto): void;
}
