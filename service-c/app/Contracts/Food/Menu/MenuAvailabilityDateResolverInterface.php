<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\MenuAvailabilityDateResultDto;
use Carbon\CarbonImmutable;

/**
 * Расчёт даты подзаголовка «Блюда на дату» по правилам offsets (MSK).
 */
interface MenuAvailabilityDateResolverInterface
{
    /**
     * Вычисляет дату меню относительно «сейчас» (или переданного момента) в Europe/Moscow.
     *
     * При отсутствии offsets на текущий weekday откатывается до 7 дней назад.
     */
    public function resolve(?CarbonImmutable $now = null): MenuAvailabilityDateResultDto;

    /**
     * Дата «Блюда на» для текущего weekday без lookback (для cron/sync).
     *
     * Если на текущий weekday нет строк в offsets — date = null.
     */
    public function resolveForCurrentWeekday(?CarbonImmutable $now = null): MenuAvailabilityDateResultDto;
}
