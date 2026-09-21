<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\DishAvailabilityFlagSyncRepositoryInterface;
use App\Contracts\Food\Menu\DishAvailabilityRepositoryInterface;
use App\Contracts\Food\Menu\DishAvailabilityScheduleRepositoryInterface;

/**
 * Composition-адаптер полного порта доступности: делегирует в schedule / flag-sync.
 */
class EloquentDishAvailabilityRepository implements DishAvailabilityRepositoryInterface
{
    public function __construct(
        private readonly DishAvailabilityScheduleRepositoryInterface $scheduleRepository,
        private readonly DishAvailabilityFlagSyncRepositoryInterface $flagSyncRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listDishesForCategory(int $restaurantId, int $categoryId): array
    {
        return $this->scheduleRepository->listDishesForCategory($restaurantId, $categoryId);
    }

    /**
     * {@inheritDoc}
     */
    public function getScheduleForDishes(array $dishIds, string $dateFrom, string $dateTo): array
    {
        return $this->scheduleRepository->getScheduleForDishes($dishIds, $dateFrom, $dateTo);
    }

    /**
     * {@inheritDoc}
     */
    public function syncDishesAvailabilityInRange(
        array $dishAvailableDates,
        string $rangeFrom,
        string $rangeTo,
        string $editableFrom,
    ): void {
        $this->scheduleRepository->syncDishesAvailabilityInRange(
            $dishAvailableDates,
            $rangeFrom,
            $rangeTo,
            $editableFrom,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function dishesBelongToCategory(array $dishIds, int $categoryId, int $restaurantId): bool
    {
        return $this->scheduleRepository->dishesBelongToCategory($dishIds, $categoryId, $restaurantId);
    }

    /**
     * {@inheritDoc}
     */
    public function clearAllDishesIsAvailable(): int
    {
        return $this->flagSyncRepository->clearAllDishesIsAvailable();
    }

    /**
     * {@inheritDoc}
     */
    public function syncDishesIsAvailableForDate(string $date): int
    {
        return $this->flagSyncRepository->syncDishesIsAvailableForDate($date);
    }

    /**
     * {@inheritDoc}
     */
    public function syncDishesIsAvailableForCategoryAndDate(int $menuCategoryId, string $date): int
    {
        return $this->flagSyncRepository->syncDishesIsAvailableForCategoryAndDate($menuCategoryId, $date);
    }

    /**
     * {@inheritDoc}
     */
    public function enableDishesIsAvailableForCategoryDates(array $categoryIdToDate): int
    {
        return $this->flagSyncRepository->enableDishesIsAvailableForCategoryDates($categoryIdToDate);
    }
}
