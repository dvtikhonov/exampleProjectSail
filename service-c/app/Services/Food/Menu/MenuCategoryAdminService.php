<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Food\Menu\MenuCategoryAdminServiceInterface;
use App\Contracts\Food\Menu\MenuCategoryRepositoryInterface;
use App\Contracts\Food\Shared\RestaurantRepositoryInterface;
use App\DTO\Food\Menu\AdminMenuCategoryDto;
use App\DTO\Food\Menu\CreateMenuCategoryDto;
use App\DTO\Food\Menu\MenuCategoryRecord;
use App\DTO\Food\Menu\UpdateMenuCategoryDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Административный CRUD категорий меню.
 */
class MenuCategoryAdminService implements MenuCategoryAdminServiceInterface
{
    public function __construct(
        private readonly MenuCategoryRepositoryInterface $menuCategoryRepository,
        private readonly RestaurantRepositoryInterface $restaurantRepository,
        private readonly MenuCatalogCacheInvalidatorInterface $catalogCacheInvalidator,
    ) {}

    /**
     * Возвращает список категорий меню для админки.
     *
     * @return list<AdminMenuCategoryDto>
     */
    public function list(?int $restaurantId = null): array
    {
        return array_map(
            fn (MenuCategoryRecord $category): AdminMenuCategoryDto => $this->mapToAdminDto($category),
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

        $this->menuCategoryRepository->syncAvailabilityOffsets($category->id, $dto->availabilityOffsets);

        $result = $this->mapToAdminDto($this->findCategoryOrFail($category->id));
        $this->catalogCacheInvalidator->invalidateAll();

        return $result;
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

        if ($dto->restaurantId !== $category->restaurantId
            && $this->menuCategoryRepository->countDishes($categoryId) > 0
        ) {
            throw new FoodDomainException(
                'Нельзя сменить ресторан: в категории есть блюда.',
                409,
            );
        }

        $this->menuCategoryRepository->update($categoryId, [
            'restaurant_id' => $dto->restaurantId,
            'name' => $dto->name,
            'sort_order' => $dto->sortOrder,
            'is_combo_available' => $dto->isComboAvailable,
        ]);

        if ($dto->availabilityOffsets !== null) {
            $this->menuCategoryRepository->syncAvailabilityOffsets($categoryId, $dto->availabilityOffsets);
        }

        $result = $this->mapToAdminDto($this->findCategoryOrFail($categoryId));
        $this->catalogCacheInvalidator->invalidateAll();

        return $result;
    }

    /**
     * Удаляет категорию меню.
     *
     * @throws FoodDomainException
     */
    public function delete(int $categoryId): void
    {
        $this->findCategoryOrFail($categoryId);

        if ($this->menuCategoryRepository->countDishes($categoryId) > 0) {
            throw new FoodDomainException(
                'Нельзя удалить категорию: в ней есть блюда.',
                409,
            );
        }

        $this->menuCategoryRepository->delete($categoryId);
        $this->catalogCacheInvalidator->invalidateAll();
    }

    /**
     * Находит категорию меню или выбрасывает доменное исключение.
     *
     * @throws FoodDomainException
     */
    private function findCategoryOrFail(int $categoryId): MenuCategoryRecord
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
     * Преобразует доменную проекцию категории в админский DTO.
     */
    private function mapToAdminDto(MenuCategoryRecord $category): AdminMenuCategoryDto
    {
        return new AdminMenuCategoryDto(
            id: $category->id,
            name: $category->name,
            restaurantId: $category->restaurantId,
            restaurantName: (string) ($category->restaurant?->name ?? ''),
            sortOrder: $category->sortOrder,
            isComboAvailable: $category->isComboAvailable,
            dishesCount: $this->menuCategoryRepository->countDishes($category->id),
            availabilityOffsets: $category->availabilityOffsets,
        );
    }
}
