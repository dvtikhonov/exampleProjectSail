<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\MenuCategoryAvailabilityOffsetDto;
use App\DTO\Food\Menu\MenuCategoryRecord;

/**
 * Репозиторий категорий меню для административного API.
 */
interface MenuCategoryRepositoryInterface
{
    /**
     * Находит категорию меню по идентификатору (с рестораном и offsets при наличии).
     */
    public function findById(int $id): ?MenuCategoryRecord;

    /**
     * Категории для select в админке (с рестораном), отсортированные для UI.
     *
     * @return list<MenuCategoryRecord>
     */
    public function listForAdmin(?int $restaurantId = null): array;

    /**
     * Создаёт категорию меню.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MenuCategoryRecord;

    /**
     * Обновляет категорию меню по идентификатору.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(int $categoryId, array $attributes): MenuCategoryRecord;

    /**
     * Полностью заменяет правила смещения доступности категории.
     *
     * @param  list<MenuCategoryAvailabilityOffsetDto>  $offsets
     */
    public function syncAvailabilityOffsets(int $categoryId, array $offsets): void;

    /**
     * Удаляет категорию меню по идентификатору.
     */
    public function delete(int $categoryId): void;

    /**
     * Возвращает число блюд в категории.
     */
    public function countDishes(int $categoryId): int;

    /**
     * Следующий порядок сортировки для категории в ресторане.
     */
    public function nextSortOrderForRestaurant(int $restaurantId): int;
}
