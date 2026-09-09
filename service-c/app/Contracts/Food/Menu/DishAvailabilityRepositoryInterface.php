<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

/**
 * Полный репозиторий доступности блюд.
 *
 * Composition ISP: объединяет schedule / flag-sync порты.
 */
interface DishAvailabilityRepositoryInterface extends
    DishAvailabilityScheduleRepositoryInterface,
    DishAvailabilityFlagSyncRepositoryInterface {}
