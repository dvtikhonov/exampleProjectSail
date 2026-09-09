<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishAvailabilityGridServiceInterface;
use App\Contracts\Food\Menu\DishAvailabilityScheduleRepositoryInterface;
use App\Contracts\Food\Menu\MenuCategoryReadRepositoryInterface;
use App\DTO\Food\Menu\DishAvailabilityGridDto;
use App\DTO\Food\Menu\DishRecord;
use App\Exceptions\Food\FoodDomainException;

/**
 * Чтение сетки графика доступности блюд.
 */
class DishAvailabilityGridService implements DishAvailabilityGridServiceInterface
{
    public function __construct(
        private readonly DishAvailabilityScheduleRepositoryInterface $availabilityRepository,
        private readonly MenuCategoryReadRepositoryInterface $menuCategoryRepository,
        private readonly DishAvailabilityScheduleWindow $scheduleWindow,
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

        [$resolvedFrom, $resolvedTo] = $this->scheduleWindow->resolveDateRange($dateFrom, $dateTo);
        $dishes = $this->availabilityRepository->listDishesForCategory($restaurantId, $categoryId);
        $dishIds = array_map(static fn (DishRecord $dish): int => $dish->id, $dishes);
        $schedule = $this->availabilityRepository->getScheduleForDishes($dishIds, $resolvedFrom, $resolvedTo);

        $scheduleForJson = [];

        foreach ($schedule as $dishId => $dates) {
            $scheduleForJson[(string) $dishId] = $dates;
        }

        return new DishAvailabilityGridDto(
            dishes: array_map(
                static fn (DishRecord $dish): array => [
                    'id' => $dish->id,
                    'name' => $dish->name,
                    'is_available' => $dish->isAvailable,
                ],
                $dishes,
            ),
            dates: $this->scheduleWindow->enumerateDates($resolvedFrom, $resolvedTo),
            schedule: $scheduleForJson,
            editableFrom: $this->scheduleWindow->editableFrom(),
        );
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
}
