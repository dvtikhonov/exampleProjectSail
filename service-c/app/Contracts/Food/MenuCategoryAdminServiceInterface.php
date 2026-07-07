<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\AdminMenuCategoryDto;
use App\DTO\Food\CreateMenuCategoryDto;
use App\DTO\Food\UpdateMenuCategoryDto;
use App\Exceptions\Food\FoodDomainException;

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
     * @throws FoodDomainException
     */
    public function show(int $categoryId): AdminMenuCategoryDto;

    /**
     * @throws FoodDomainException
     */
    public function create(CreateMenuCategoryDto $dto): AdminMenuCategoryDto;

    /**
     * @throws FoodDomainException
     */
    public function update(int $categoryId, UpdateMenuCategoryDto $dto): AdminMenuCategoryDto;

    /**
     * @throws FoodDomainException
     */
    public function delete(int $categoryId): void;
}
