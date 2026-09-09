<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishAvailabilityScheduleRepositoryInterface;
use App\Contracts\Food\Menu\DishAvailabilityScheduleWriterInterface;
use App\Contracts\Food\Menu\DishAvailabilitySyncServiceInterface;
use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Food\Menu\MenuCategoryReadRepositoryInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Menu\DishAvailabilityChangeDto;
use App\DTO\Food\Menu\DishAvailabilityUpdateDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Синхронизация графика доступности блюд (включая сегодня).
 */
class DishAvailabilityScheduleWriter implements DishAvailabilityScheduleWriterInterface
{
    public function __construct(
        private readonly DishAvailabilityScheduleRepositoryInterface $availabilityRepository,
        private readonly MenuCategoryReadRepositoryInterface $menuCategoryRepository,
        private readonly DishAvailabilitySyncServiceInterface $availabilitySyncService,
        private readonly MenuAvailabilityDateResolverInterface $availabilityDateResolver,
        private readonly MenuCatalogCacheInvalidatorInterface $catalogCacheInvalidator,
        private readonly TransactionManagerInterface $transactionManager,
        private readonly DishAvailabilityScheduleWindow $scheduleWindow,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function syncSchedule(DishAvailabilityUpdateDto $dto): void
    {
        $this->assertCategoryBelongsToRestaurant($dto->categoryId, $dto->restaurantId);

        [$rangeFrom, $rangeTo] = $this->scheduleWindow->resolveDateRange($dto->dateFrom, $dto->dateTo);
        $editableFrom = $this->scheduleWindow->editableFrom();

        $dishIds = array_map(
            static fn (DishAvailabilityChangeDto $change): int => $change->dishId,
            $dto->changes,
        );

        if (! $this->availabilityRepository->dishesBelongToCategory(
            $dishIds,
            $dto->categoryId,
            $dto->restaurantId,
        )) {
            throw new FoodDomainException('Одно или несколько блюд не принадлежат выбранной категории.', 422);
        }

        foreach ($dto->changes as $change) {
            $this->assertEditableDates($change->dates, $editableFrom, $rangeFrom, $rangeTo);
        }

        $dishAvailableDates = [];

        foreach ($dto->changes as $change) {
            $dishAvailableDates[$change->dishId] = $change->dates;
        }

        $this->transactionManager->run(function () use ($dishAvailableDates, $rangeFrom, $rangeTo, $editableFrom): void {
            $this->availabilityRepository->syncDishesAvailabilityInRange(
                $dishAvailableDates,
                $rangeFrom,
                $rangeTo,
                $editableFrom,
            );
        });

        $this->catalogCacheInvalidator->invalidateRestaurant($dto->restaurantId);

        $menuDate = $this->availabilityDateResolver->resolveForCurrentWeekday();

        if ($menuDate->date === null) {
            return;
        }

        $this->availabilitySyncService->syncForCurrentWeekdayCategoryOffsets();
    }

    /**
     * Проверяет, что категория принадлежит ресторану.
     *
     * @throws FoodDomainException
     */
    private function assertCategoryBelongsToRestaurant(int $categoryId, int $restaurantId): void
    {
        $category = $this->menuCategoryRepository->findById($categoryId);

        if ($category === null || $category->restaurantId !== $restaurantId) {
            throw new FoodDomainException('Категория меню не найдена для выбранного ресторана.', 422);
        }
    }

    /**
     * Проверяет, что даты доступности можно редактировать.
     *
     * @param  list<string>  $dates
     *
     * @throws FoodDomainException
     */
    private function assertEditableDates(
        array $dates,
        string $editableFrom,
        string $rangeFrom,
        string $rangeTo,
    ): void {
        foreach ($dates as $date) {
            if ($date < $editableFrom) {
                throw new FoodDomainException(
                    'Нельзя изменять доступность на прошедшие даты.',
                    422,
                );
            }

            if ($date < $rangeFrom || $date > $rangeTo) {
                throw new FoodDomainException(
                    'Дата вне допустимого диапазона графика.',
                    422,
                );
            }
        }
    }
}
