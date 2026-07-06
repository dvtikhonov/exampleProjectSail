<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishAvailabilityRepositoryInterface;
use App\Contracts\Food\DishAvailabilityScheduleServiceInterface;
use App\Contracts\Food\MenuCategoryRepositoryInterface;
use App\DTO\Food\DishAvailabilityChangeDto;
use App\DTO\Food\DishAvailabilityGridDto;
use App\DTO\Food\DishAvailabilityUpdateDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Dish;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * График доступности блюд: чтение сетки и синхронизация будущих дат.
 */
class DishAvailabilityScheduleService implements DishAvailabilityScheduleServiceInterface
{
    private const string TIMEZONE = 'Europe/Moscow';

    /** Максимум дней вперёд от сегодня (включительно) в графике. */
    private const int DAYS_FORWARD = 30;

    public function __construct(
        private readonly DishAvailabilityRepositoryInterface $availabilityRepository,
        private readonly MenuCategoryRepositoryInterface $menuCategoryRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getGrid(
        int $restaurantId,
        int $categoryId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): DishAvailabilityGridDto {
        $this->assertCategoryBelongsToRestaurant($categoryId, $restaurantId);

        [$resolvedFrom, $resolvedTo] = $this->resolveDateRange($dateFrom, $dateTo);
        $dishes = $this->availabilityRepository->listDishesForCategory($restaurantId, $categoryId);
        $dishIds = array_map(static fn (Dish $dish): int => $dish->id, $dishes);
        $schedule = $this->availabilityRepository->getScheduleForDishes($dishIds, $resolvedFrom, $resolvedTo);

        $scheduleForJson = [];

        foreach ($schedule as $dishId => $dates) {
            $scheduleForJson[(string) $dishId] = $dates;
        }

        return new DishAvailabilityGridDto(
            dishes: array_map(
                static fn (Dish $dish): array => [
                    'id' => $dish->id,
                    'name' => $dish->name,
                    'is_available' => $dish->is_available,
                ],
                $dishes,
            ),
            dates: $this->enumerateDates($resolvedFrom, $resolvedTo),
            schedule: $scheduleForJson,
            editableFrom: $this->editableFrom(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function syncSchedule(DishAvailabilityUpdateDto $dto): void
    {
        $this->assertCategoryBelongsToRestaurant($dto->categoryId, $dto->restaurantId);

        [$rangeFrom, $rangeTo] = $this->resolveDateRange($dto->dateFrom, $dto->dateTo);
        $editableFrom = $this->editableFrom();

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

        DB::transaction(function () use ($dto, $rangeFrom, $rangeTo, $editableFrom): void {
            foreach ($dto->changes as $change) {
                $this->availabilityRepository->syncDishAvailabilityInRange(
                    $change->dishId,
                    $change->dates,
                    $rangeFrom,
                    $rangeTo,
                    $editableFrom,
                );
            }
        });
    }

    /**
     * @throws FoodDomainException
     */
    private function assertCategoryBelongsToRestaurant(int $categoryId, int $restaurantId): void
    {
        $category = $this->menuCategoryRepository->findById($categoryId);

        if ($category === null || (int) $category->restaurant_id !== $restaurantId) {
            throw new FoodDomainException('Категория меню не найдена для выбранного ресторана.', 422);
        }
    }

    /**
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
                    'Нельзя изменять доступность на сегодня или прошедшие даты.',
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

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(?string $dateFrom, ?string $dateTo): array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $editableFrom = $today->addDay();
        $maxTo = $today->addDays(self::DAYS_FORWARD);

        $from = $dateFrom ?? $editableFrom->toDateString();
        $to = $dateTo ?? $maxTo->toDateString();

        if ($from < $editableFrom->toDateString()) {
            $from = $editableFrom->toDateString();
        }

        if ($to > $maxTo->toDateString()) {
            $to = $maxTo->toDateString();
        }

        if ($from > $to) {
            throw new FoodDomainException('Дата начала диапазона не может быть позже даты окончания.', 422);
        }

        return [$from, $to];
    }

    private function editableFrom(): string
    {
        return CarbonImmutable::now(self::TIMEZONE)->addDay()->toDateString();
    }

    /**
     * @return list<string>
     */
    private function enumerateDates(string $from, string $to): array
    {
        $dates = [];
        $current = CarbonImmutable::parse($from, self::TIMEZONE);
        $end = CarbonImmutable::parse($to, self::TIMEZONE);

        while ($current->lte($end)) {
            $dates[] = $current->toDateString();
            $current = $current->addDay();
        }

        return $dates;
    }
}
