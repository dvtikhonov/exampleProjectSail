<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

/**
 * Сервис графика доступности блюд по датам.
 *
 * Composition ISP: объединяет чтение сетки и запись графика.
 */
interface DishAvailabilityScheduleServiceInterface extends DishAvailabilityGridServiceInterface, DishAvailabilityScheduleWriterInterface {}
