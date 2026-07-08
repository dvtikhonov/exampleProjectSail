<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishAvailabilityRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Синхронизация флага is_available у блюд по графику на указанную дату.
 */
class DishAvailabilitySyncService
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function __construct(
        private readonly DishAvailabilityRepositoryInterface $availabilityRepository,
    ) {}

    /**
     * Выставляет is_available по графику на сегодняшнюю дату (MSK).
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncForToday(): int
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();

        return $this->availabilityRepository->syncDishesIsAvailableForDate($today);
    }
}
