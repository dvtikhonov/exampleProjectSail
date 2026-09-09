<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\DishRecord;

/**
 * Репозиторий графика доступности блюд (UI / PhotoText).
 */
interface DishAvailabilityScheduleRepositoryInterface
{
    /**
     * Блюда категории ресторана для отображения в графике.
     *
     * @return list<DishRecord>
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
     * Пакетно синхронизирует доступные даты блюд в редактируемой части диапазона.
     * Число SQL-запросов не зависит от количества блюд в payload (delete + insert chunks).
     *
     * @param  array<int, list<string>>  $dishAvailableDates  dish_id => [Y-m-d, ...]
     */
    public function syncDishesAvailabilityInRange(
        array $dishAvailableDates,
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
}
