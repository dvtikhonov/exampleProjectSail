<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\MenuCategory;

/**
 * Репозиторий категорий меню для административного API.
 */
interface MenuCategoryRepositoryInterface
{
    /**
     * Находит категорию меню по идентификатору.
     */
    public function findById(int $id): ?MenuCategory;

    /**
     * Категории для select в админке (с рестораном), отсортированные для UI.
     *
     * @return list<MenuCategory>
     */
    public function listForAdmin(?int $restaurantId = null): array;

    /**
     * Создаёт категорию меню.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MenuCategory;

    /**
     * Обновляет категорию меню.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(MenuCategory $category, array $attributes): MenuCategory;

    /**
     * Удаляет категорию меню.
     */
    public function delete(MenuCategory $category): void;

    /**
     * Возвращает число блюд в категории.
     */
    public function countDishes(int $categoryId): int;

    /**
     * Следующий порядок сортировки для категории в ресторане.
     */
    public function nextSortOrderForRestaurant(int $restaurantId): int;
}
