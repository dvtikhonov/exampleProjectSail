<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\DishRecord;

/**
 * Репозиторий графика доступности блюд по датам.
 */
interface DishAvailabilityRepositoryInterface
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

    /**
     * Сбрасывает is_available = false у всех активных блюд (без soft delete).
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function clearAllDishesIsAvailable(): int;

    /**
     * Синхронизирует max_dishes.is_available по графику на указанную дату.
     * Учитываются только активные блюда (без soft delete).
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncDishesIsAvailableForDate(string $date): int;

    /**
     * Синхронизирует is_available блюд одной категории по графику на дату.
     * Блюда других категорий не изменяются. Учитываются только активные (без soft delete).
     *
     * @return int Количество обновлённых записей max_dishes
     */
    public function syncDishesIsAvailableForCategoryAndDate(int $menuCategoryId, string $date): int;

    /**
     * Включает is_available у блюд, у которых есть запись графика на дату своей категории.
     * Один SELECT + один UPDATE независимо от числа категорий.
     *
     * @param  array<int, string>  $categoryIdToDate  menu_category_id => Y-m-d
     * @return int Количество обновлённых записей max_dishes
     */
    public function enableDishesIsAvailableForCategoryDates(array $categoryIdToDate): int;
}
