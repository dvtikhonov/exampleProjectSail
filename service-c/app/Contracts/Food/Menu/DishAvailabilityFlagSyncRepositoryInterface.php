<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

/**
 * Репозиторий синхронизации флага is_available по графику.
 */
interface DishAvailabilityFlagSyncRepositoryInterface
{
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
