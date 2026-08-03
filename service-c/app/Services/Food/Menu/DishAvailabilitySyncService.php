<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishAvailabilityRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryAvailabilityOffsetRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Синхронизация флага is_available у блюд по графику доступности.
 */
class DishAvailabilitySyncService
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function __construct(
        private readonly DishAvailabilityRepositoryInterface $availabilityRepository,
        private readonly MenuCategoryAvailabilityOffsetRepositoryInterface $offsetRepository,
    ) {}

    /**
     * Выставляет is_available по графику на указанную дату (Y-m-d) для всех блюд.
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncForDate(string $date): int
    {
        return $this->availabilityRepository->syncDishesIsAvailableForDate($date);
    }

    /**
     * Выставляет is_available по offsets категорий на текущий weekday (MSK).
     *
     * Сначала сбрасывает is_available у всех блюд, затем одним пакетом включает
     * блюда категорий с offset: дата = сегодня + offset_days — по графику.
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncForCurrentWeekdayCategoryOffsets(?CarbonImmutable $now = null): int
    {
        $referenceDate = ($now ?? CarbonImmutable::now(self::TIMEZONE))
            ->timezone(self::TIMEZONE)
            ->startOfDay();

        $categoryOffsets = $this->offsetRepository->listCategoryOffsetsForWeekday(
            $referenceDate->dayOfWeekIso,
        );

        $updatedCount = $this->availabilityRepository->clearAllDishesIsAvailable();

        /** @var array<int, string> $categoryIdToDate */
        $categoryIdToDate = [];

        foreach ($categoryOffsets as $categoryOffset) {
            $categoryIdToDate[$categoryOffset->menuCategoryId] = $referenceDate
                ->addDays($categoryOffset->offsetDays)
                ->format('Y-m-d');
        }

        $updatedCount += $this->availabilityRepository->enableDishesIsAvailableForCategoryDates(
            $categoryIdToDate,
        );

        return $updatedCount;
    }

    /**
     * Выставляет is_available по графику на сегодняшнюю дату (MSK).
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncForToday(): int
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();

        return $this->syncForDate($today);
    }
}
