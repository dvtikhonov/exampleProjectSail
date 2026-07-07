<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\AdminMenuCategoryDto;
use App\DTO\Food\CreateMenuCategoryDto;
use App\DTO\Food\UpdateMenuCategoryDto;
use App\Models\MenuCategory;

/**
 * Сервис административного CRUD категорий меню.
 */
interface MenuCategoryAdminServiceInterface
{
    /**
     * @return list<AdminMenuCategoryDto>
     */
    public function list(?int $restaurantId = null): array;

    /**
     * @throws \App\Exceptions\Food\FoodDomainException
     */
    public function show(int $categoryId): AdminMenuCategoryDto;

    /**
     * @throws \App\Exceptions\Food\FoodDomainException
     */
    public function create(CreateMenuCategoryDto $dto): AdminMenuCategoryDto;

    /**
     * @throws \App\Exceptions\Food\FoodDomainException
     */
    public function update(int $categoryId, UpdateMenuCategoryDto $dto): AdminMenuCategoryDto;

    /**
     * @throws \App\Exceptions\Food\FoodDomainException
     */
    public function delete(int $categoryId): void;
}
