<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\Dish;

/**
 * Репозиторий графика доступности блюд по датам.
 */
interface DishAvailabilityRepositoryInterface
{
    /**
     * Блюда категории ресторана для отображения в графике.
     *
     * @return list<Dish>
     */
    public function listDishesForCategory(int $restaurantId, int $categoryId): array;

    /**
     * Доступные даты по блюдам в диапазоне.
     *
     * @param  list<int>  $dishIds
     * @return array<int, list<string>> dish_id => [Y-m-d, ...]
     */
    public function getScheduleForDishes(array $dishIds, string $dateFrom, string $dateTo): array;

    /**
     * Синхронизирует доступные даты блюда в редактируемой части диапазона.
     *
     * @param  list<string>  $availableDates
     */
    public function syncDishAvailabilityInRange(
        int $dishId,
        array $availableDates,
        string $rangeFrom,
        string $rangeTo,
        string $editableFrom,
    ): void;

    /**
     * Проверяет, что все блюда принадлежат категории ресторана.
     *
     * @param  list<int>  $dishIds
     */
    public function dishesBelongToCategory(array $dishIds, int $categoryId, int $restaurantId): bool;

    /**
     * Синхронизирует max_dishes.is_available по графику на указанную дату.
     * Учитываются только активные блюда (без soft delete).
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncDishesIsAvailableForDate(string $date): int;
}
