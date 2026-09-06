<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use Carbon\CarbonImmutable;

/**
 * Синхронизация флага is_available у блюд по графику доступности.
 */
interface DishAvailabilitySyncServiceInterface
{
    /**
     * Выставляет is_available по графику на указанную дату (Y-m-d) для всех блюд.
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncForDate(string $date): int;

    /**
     * Выставляет is_available по offsets категорий на текущий weekday (MSK).
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncForCurrentWeekdayCategoryOffsets(?CarbonImmutable $now = null): int;

    /**
     * Выставляет is_available по графику на сегодняшнюю дату (MSK).
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncForToday(): int;
}
