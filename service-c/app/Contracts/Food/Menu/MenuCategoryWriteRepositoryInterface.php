<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\CreateMenuCategoryDto;
use App\DTO\Food\Menu\MenuCategoryAvailabilityOffsetDto;
use App\DTO\Food\Menu\MenuCategoryRecord;
use App\DTO\Food\Menu\UpdateMenuCategoryDto;

/**
 * Запись категорий меню для административного CRUD.
 */
interface MenuCategoryWriteRepositoryInterface
{
    /**
     * Создаёт категорию меню (sort_order назначается репозиторием).
     */
    public function create(CreateMenuCategoryDto $dto): MenuCategoryRecord;

    /**
     * Обновляет категорию меню по идентификатору.
     */
    public function update(int $categoryId, UpdateMenuCategoryDto $dto): MenuCategoryRecord;

    /**
     * Удаляет категорию меню по идентификатору.
     */
    public function delete(int $categoryId): void;

    /**
     * Полностью заменяет правила смещения доступности категории.
     *
     * @param  list<MenuCategoryAvailabilityOffsetDto>  $offsets
     */
    public function syncAvailabilityOffsets(int $categoryId, array $offsets): void;

    /**
     * Следующий порядок сортировки для категории в ресторане.
     */
    public function nextSortOrderForRestaurant(int $restaurantId): int;
}
