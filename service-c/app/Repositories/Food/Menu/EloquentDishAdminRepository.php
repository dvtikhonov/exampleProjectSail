<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\DishAdminBulkRepositoryInterface;
use App\Contracts\Food\Menu\DishAdminReadRepositoryInterface;
use App\Contracts\Food\Menu\DishAdminRepositoryInterface;
use App\Contracts\Food\Menu\DishAdminWriteRepositoryInterface;
use App\DTO\Food\Menu\CreateDishDto;
use App\DTO\Food\Menu\DishAdminListResultDto;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Menu\UpdateDishDto;

/**
 * Composition-адаптер полного admin-порта блюд: делегирует в read / write / bulk.
 */
class EloquentDishAdminRepository implements DishAdminRepositoryInterface
{
    public function __construct(
        private readonly DishAdminReadRepositoryInterface $readRepository,
        private readonly DishAdminWriteRepositoryInterface $writeRepository,
        private readonly DishAdminBulkRepositoryInterface $bulkRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?DishRecord
    {
        return $this->readRepository->findById($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByNameAndMenuCategoryId(string $name, int $menuCategoryId): ?DishRecord
    {
        return $this->readRepository->findByNameAndMenuCategoryId($name, $menuCategoryId);
    }

    /**
     * {@inheritDoc}
     */
    public function findByNamesAndMenuCategoryId(array $names, int $menuCategoryId): array
    {
        return $this->readRepository->findByNamesAndMenuCategoryId($names, $menuCategoryId);
    }

    /**
     * {@inheritDoc}
     */
    public function listForAdmin(
        ?int $restaurantId,
        ?int $categoryId,
        ?string $nameSearch = null,
        ?bool $isAvailable = null,
    ): DishAdminListResultDto {
        return $this->readRepository->listForAdmin(
            $restaurantId,
            $categoryId,
            $nameSearch,
            $isAvailable,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function existsInDraftCarts(int $dishId): bool
    {
        return $this->readRepository->existsInDraftCarts($dishId);
    }

    /**
     * {@inheritDoc}
     */
    public function create(CreateDishDto $dto): DishRecord
    {
        return $this->writeRepository->create($dto);
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $dishId, UpdateDishDto $dto): DishRecord
    {
        return $this->writeRepository->update($dishId, $dto);
    }

    /**
     * {@inheritDoc}
     */
    public function updateImageUrl(int $dishId, string $imageUrl): DishRecord
    {
        return $this->writeRepository->updateImageUrl($dishId, $imageUrl);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $dishId): void
    {
        $this->writeRepository->delete($dishId);
    }

    /**
     * {@inheritDoc}
     */
    public function updatePricesByIds(array $pricesById): void
    {
        $this->bulkRepository->updatePricesByIds($pricesById);
    }

    /**
     * {@inheritDoc}
     */
    public function createMany(array $dtos): array
    {
        return $this->bulkRepository->createMany($dtos);
    }

    /**
     * {@inheritDoc}
     */
    public function updateImageUrlsByIds(array $imageUrlsById): void
    {
        $this->bulkRepository->updateImageUrlsByIds($imageUrlsById);
    }
}
