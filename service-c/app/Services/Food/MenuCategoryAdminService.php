<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\MenuCategoryAdminServiceInterface;
use App\Contracts\Food\MenuCategoryRepositoryInterface;
use App\Contracts\Food\RestaurantRepositoryInterface;
use App\DTO\Food\AdminMenuCategoryDto;
use App\DTO\Food\CreateMenuCategoryDto;
use App\DTO\Food\UpdateMenuCategoryDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\MenuCategory;

/**
 * Административный CRUD категорий меню.
 */
class MenuCategoryAdminService implements MenuCategoryAdminServiceInterface
{
    public function __construct(
        private readonly MenuCategoryRepositoryInterface $menuCategoryRepository,
        private readonly RestaurantRepositoryInterface $restaurantRepository,
    ) {}

    /**
     * Возвращает список категорий меню для админки.
     *
     * @return list<AdminMenuCategoryDto>
     */
    public function list(?int $restaurantId = null): array
    {
        return array_map(
            fn (MenuCategory $category): AdminMenuCategoryDto => $this->mapToAdminDto($category),
            $this->menuCategoryRepository->listForAdmin($restaurantId),
        );
    }

    /**
     * Возвращает категорию меню по идентификатору.
     *
     * @throws FoodDomainException
     */
    public function show(int $categoryId): AdminMenuCategoryDto
    {
        return $this->mapToAdminDto($this->findCategoryOrFail($categoryId));
    }

    /**
     * Создаёт категорию меню.
     *
     * @throws FoodDomainException
     */
    public function create(CreateMenuCategoryDto $dto): AdminMenuCategoryDto
    {
        $this->assertRestaurantExists($dto->restaurantId);

        $category = $this->menuCategoryRepository->create([
            'restaurant_id' => $dto->restaurantId,
            'name' => $dto->name,
            'sort_order' => $dto->sortOrder,
            'is_combo_available' => $dto->isComboAvailable,
        ]);

        return $this->mapToAdminDto($category->load('restaurant'));
    }

    /**
     * Обновляет категорию меню.
     *
     * @throws FoodDomainException
     */
    public function update(int $categoryId, UpdateMenuCategoryDto $dto): AdminMenuCategoryDto
    {
        $category = $this->findCategoryOrFail($categoryId);
        $this->assertRestaurantExists($dto->restaurantId);

        if ($dto->restaurantId !== (int) $category->restaurant_id
            && $this->menuCategoryRepository->countDishes($categoryId) > 0
        ) {
            throw new FoodDomainException(
                'Нельзя сменить ресторан: в категории есть блюда.',
                409,
            );
        }

        $category = $this->menuCategoryRepository->update($category, [
            'restaurant_id' => $dto->restaurantId,
            'name' => $dto->name,
            'sort_order' => $dto->sortOrder,
            'is_combo_available' => $dto->isComboAvailable,
        ]);

        return $this->mapToAdminDto($category);
    }

    /**
     * Удаляет категорию меню.
     *
     * @throws FoodDomainException
     */
    public function delete(int $categoryId): void
    {
        $category = $this->findCategoryOrFail($categoryId);

        if ($this->menuCategoryRepository->countDishes($categoryId) > 0) {
            throw new FoodDomainException(
                'Нельзя удалить категорию: в ней есть блюда.',
                409,
            );
        }

        $this->menuCategoryRepository->delete($category);
    }

    /**
     * Находит категорию меню или выбрасывает доменное исключение.
     *
     * @throws FoodDomainException
     */
    private function findCategoryOrFail(int $categoryId): MenuCategory
    {
        $category = $this->menuCategoryRepository->findById($categoryId);

        if ($category === null) {
            throw new FoodDomainException('Категория меню не найдена.', 404);
        }

        return $category;
    }

    /**
     * Проверяет существование ресторана.
     *
     * @throws FoodDomainException
     */
    private function assertRestaurantExists(int $restaurantId): void
    {
        if ($this->restaurantRepository->findActiveById($restaurantId) === null) {
            throw new FoodDomainException('Ресторан не найден.', 422);
        }
    }

    /**
     * Преобразует модель категории в админский DTO.
     */
    private function mapToAdminDto(MenuCategory $category): AdminMenuCategoryDto
    {
        return new AdminMenuCategoryDto(
            id: $category->id,
            name: $category->name,
            restaurantId: (int) $category->restaurant_id,
            restaurantName: (string) $category->restaurant?->name,
            sortOrder: (int) $category->sort_order,
            isComboAvailable: (bool) $category->is_combo_available,
            dishesCount: $this->menuCategoryRepository->countDishes($category->id),
        );
    }
}
