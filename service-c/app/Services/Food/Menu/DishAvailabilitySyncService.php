<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishAvailabilityFlagSyncRepositoryInterface;
use App\Contracts\Food\Menu\DishAvailabilitySyncServiceInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Food\Menu\MenuCategoryAvailabilityOffsetRepositoryInterface;
use App\Contracts\Shared\ClockInterface;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Синхронизация флага is_available у блюд по графику доступности.
 */
class DishAvailabilitySyncService implements DishAvailabilitySyncServiceInterface
{
    private const string TIMEZONE = 'Europe/Moscow';

    public function __construct(
        private readonly DishAvailabilityFlagSyncRepositoryInterface $availabilityRepository,
        private readonly MenuCategoryAvailabilityOffsetRepositoryInterface $offsetRepository,
        private readonly MenuCatalogCacheInvalidatorInterface $catalogCacheInvalidator,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Выставляет is_available по графику на указанную дату (Y-m-d) для всех блюд.
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncForDate(string $date): int
    {
        $updatedCount = $this->availabilityRepository->syncDishesIsAvailableForDate($date);
        $this->catalogCacheInvalidator->invalidateAll();

        return $updatedCount;
    }

    /**
     * Выставляет is_available по offsets категорий на текущий weekday (MSK).
     *
     * Сначала сбрасывает is_available у всех блюд, затем одним пакетом включает
     * блюда категорий с offset: дата = сегодня + offset_days — по графику.
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncForCurrentWeekdayCategoryOffsets(?DateTimeInterface $now = null): int
    {
        $referenceDate = $this->toMoscowStartOfDay($now);

        $categoryOffsets = $this->offsetRepository->listCategoryOffsetsForWeekday(
            (int) $referenceDate->format('N'),
        );

        $updatedCount = $this->availabilityRepository->clearAllDishesIsAvailable();

        /** @var array<int, string> $categoryIdToDate */
        $categoryIdToDate = [];

        foreach ($categoryOffsets as $categoryOffset) {
            $categoryIdToDate[$categoryOffset->menuCategoryId] = $this
                ->addDays($referenceDate, $categoryOffset->offsetDays)
                ->format('Y-m-d');
        }

        $updatedCount += $this->availabilityRepository->enableDishesIsAvailableForCategoryDates(
            $categoryIdToDate,
        );

        $this->catalogCacheInvalidator->invalidateAll();

        return $updatedCount;
    }

    /**
     * Выставляет is_available по графику на сегодняшнюю дату (MSK).
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncForToday(): int
    {
        $today = $this->toMoscowStartOfDay(null)->format('Y-m-d');

        return $this->syncForDate($today);
    }

    /**
     * Нормализует «сейчас» к началу календарного дня Europe/Moscow.
     */
    private function toMoscowStartOfDay(?DateTimeInterface $now): DateTimeImmutable
    {
        $instant = DateTimeImmutable::createFromInterface($now ?? $this->clock->now());

        return $instant
            ->setTimezone(new DateTimeZone(self::TIMEZONE))
            ->setTime(0, 0, 0);
    }

    /**
     * Добавляет календарные дни.
     */
    private function addDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        if ($days === 0) {
            return $date;
        }

        if ($days < 0) {
            return $date->sub(new DateInterval('P'.abs($days).'D'));
        }

        return $date->add(new DateInterval('P'.$days.'D'));
    }
}
