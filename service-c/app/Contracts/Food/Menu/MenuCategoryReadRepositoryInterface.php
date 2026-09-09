<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\MenuCategoryRecord;

/**
 * Чтение категорий меню для административных и PhotoText-сценариев.
 */
interface MenuCategoryReadRepositoryInterface
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
     * Возвращает число блюд в категории.
     */
    public function countDishes(int $categoryId): int;
}
