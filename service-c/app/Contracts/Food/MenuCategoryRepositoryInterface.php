<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\MenuCategory;

/**
 * Репозиторий категорий меню для административного API.
 */
interface MenuCategoryRepositoryInterface
{
    public function findById(int $id): ?MenuCategory;

    /**
     * Категории для select в админке (с рестораном), отсортированные для UI.
     *
     * @return list<MenuCategory>
     */
    public function listForAdmin(?int $restaurantId = null): array;
}
