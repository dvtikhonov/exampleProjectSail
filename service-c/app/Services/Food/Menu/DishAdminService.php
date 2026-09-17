<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishAdminPhotoCoordinatorInterface;
use App\Contracts\Food\Menu\DishAdminReadRepositoryInterface;
use App\Contracts\Food\Menu\DishAdminServiceInterface;
use App\Contracts\Food\Menu\DishAdminWriteRepositoryInterface;
use App\Contracts\Food\Menu\DishImageUrlResolverInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Food\Menu\MenuCategoryReadRepositoryInterface;
use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Menu\AdminDishDto;
use App\DTO\Food\Menu\CreateDishDto;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Menu\UpdateDishDto;
use App\DTO\Shared\UploadedFileDto;
use App\Enums\Food\Menu\AdminDishAvailabilityFilter;
use App\Enums\Food\Menu\DishVatRate;
use App\Exceptions\Food\FoodDomainException;

/**
 * Административный CRUD блюд меню.
 */
class DishAdminService implements DishAdminServiceInterface
{
    public function __construct(
        private readonly DishAdminReadRepositoryInterface $dishReadRepository,
        private readonly DishAdminWriteRepositoryInterface $dishWriteRepository,
        private readonly MenuCategoryReadRepositoryInterface $menuCategoryRepository,
        private readonly DishAdminPhotoCoordinatorInterface $photoCoordinator,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
        private readonly FoodMoneyFormatterInterface $moneyFormatter,
        private readonly MenuCatalogCacheInvalidatorInterface $catalogCacheInvalidator,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * Возвращает список блюд для админки.
     *
     * @return array{
     *     dishes: list<AdminDishDto>,
     *     total: int,
     *     truncated: bool
     * }
     */
    public function list(
        ?int $restaurantId = null,
        ?int $categoryId = null,
        ?string $nameSearch = null,
        AdminDishAvailabilityFilter $availability = AdminDishAvailabilityFilter::All,
    ): array {
        $result = $this->dishReadRepository->listForAdmin(
            $restaurantId,
            $categoryId,
            $nameSearch,
            $availability->toIsAvailable(),
        );

        return [
            'dishes' => array_map(
                fn (DishRecord $dish): AdminDishDto => $this->mapToAdminDto($dish),
                $result->items,
            ),
            'total' => $result->total,
            'truncated' => $result->truncated,
        ];
    }

    /**
     * Возвращает блюдо по идентификатору для админки.
     *
     * @throws FoodDomainException
     */
    public function show(int $dishId): AdminDishDto
    {
        return $this->mapToAdminDto($this->findDishOrFail($dishId));
    }

    /**
     * Создаёт блюдо.
     *
     * @throws FoodDomainException
     */
    public function create(CreateDishDto $dto, UploadedFileDto $photo): AdminDishDto
    {
        $this->assertMenuCategoryExists($dto->menuCategoryId);

        $result = $this->transactionManager->run(function () use ($dto, $photo): AdminDishDto {
            $dish = $this->dishWriteRepository->create($dto);
            $imagePath = $this->photoCoordinator->assignUploadedPhoto($dish->id, $photo);
            $dish = $this->dishWriteRepository->updateImageUrl($dish->id, $imagePath);

            return $this->mapToAdminDto($dish);
        });

        $this->catalogCacheInvalidator->invalidateAll();

        return $result;
    }

    /**
     * Обновляет блюдо.
     *
     * @throws FoodDomainException
     */
    public function update(int $dishId, UpdateDishDto $dto, ?UploadedFileDto $photo = null): AdminDishDto
    {
        $dish = $this->findDishOrFail($dishId);
        $this->assertMenuCategoryExists($dto->menuCategoryId);

        $result = $this->transactionManager->run(function () use ($dish, $dto, $photo): AdminDishDto {
            $previousImagePath = $dish->imageUrl;
            $updated = $this->dishWriteRepository->update($dish->id, $dto);

            if ($photo !== null) {
                $imagePath = $this->photoCoordinator->assignUploadedPhoto($dish->id, $photo);
                $updated = $this->dishWriteRepository->updateImageUrl($dish->id, $imagePath);
                $this->photoCoordinator->deletePhotoIfExists($previousImagePath);
            }

            return $this->mapToAdminDto($updated);
        });

        $this->catalogCacheInvalidator->invalidateAll();

        return $result;
    }

    /**
     * Удаляет блюдо.
     *
     * @throws FoodDomainException
     */
    public function delete(int $dishId): void
    {
        $this->findDishOrFail($dishId);

        if ($this->dishReadRepository->existsInDraftCarts($dishId)) {
            throw new FoodDomainException(
                'Нельзя удалить блюдо: оно есть в активных корзинах пользователей.',
                409,
            );
        }

        $this->transactionManager->run(function () use ($dishId): void {
            $this->dishWriteRepository->delete($dishId);
        });

        $this->catalogCacheInvalidator->invalidateAll();
    }

    /**
     * Находит блюдо или выбрасывает доменное исключение.
     *
     * @throws FoodDomainException
     */
    private function findDishOrFail(int $dishId): DishRecord
    {
        $dish = $this->dishReadRepository->findById($dishId);

        if ($dish === null) {
            throw new FoodDomainException('Блюдо не найдено.', 404);
        }

        return $dish;
    }

    /**
     * Проверяет существование категории меню.
     *
     * @throws FoodDomainException
     */
    private function assertMenuCategoryExists(int $menuCategoryId): void
    {
        if ($this->menuCategoryRepository->findById($menuCategoryId) === null) {
            throw new FoodDomainException('Категория меню не найдена.', 422);
        }
    }

    /**
     * Преобразует доменную проекцию блюда в админский DTO.
     */
    private function mapToAdminDto(DishRecord $dish): AdminDishDto
    {
        $category = $dish->menuCategory;
        $restaurant = $category?->restaurant;
        $weightUnit = $dish->weightUnit;
        $vatRate = DishVatRate::fromValue($dish->vatRate);

        return new AdminDishDto(
            id: $dish->id,
            name: $dish->name,
            description: $dish->description,
            menuCategoryId: $dish->menuCategoryId,
            menuCategoryName: (string) ($category?->name ?? ''),
            restaurantId: (int) ($restaurant?->id ?? 0),
            restaurantName: (string) ($restaurant?->name ?? ''),
            weight: $this->formatWeight($dish->weight),
            weightUnit: $weightUnit->value,
            weightUnitLabel: $weightUnit->label(),
            price: $this->moneyFormatter->format($dish->price),
            vatRate: $vatRate->value(),
            vatRateLabel: $vatRate->label(),
            isAvailable: $dish->isAvailable,
            imageUrl: $this->imageUrlResolver->resolvePublicUrl($dish->id, $dish->imageUrl),
        );
    }

    /**
     * Форматирует вес блюда для ответа API.
     */
    private function formatWeight(mixed $weight): string
    {
        return (string) (int) round((float) $weight);
    }
}
