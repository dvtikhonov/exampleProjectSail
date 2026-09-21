<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\DishAdminWriteRepositoryInterface;
use App\DTO\Food\Menu\CreateDishDto;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Menu\UpdateDishDto;
use App\Models\Food\Dish;

/**
 * Eloquent-реализация write-порта административного репозитория блюд.
 */
class EloquentDishAdminWriteRepository implements DishAdminWriteRepositoryInterface
{
    public function __construct(
        private readonly DishMapper $dishMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function create(CreateDishDto $dto): DishRecord
    {
        $dish = Dish::query()->create($this->dishMapper->toCreateAttributes($dto));

        return $this->dishMapper->toRecord($dish->load(['menuCategory.restaurant']));
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $dishId, UpdateDishDto $dto): DishRecord
    {
        $dish = Dish::query()->findOrFail($dishId);
        $dish->update($this->dishMapper->toUpdateAttributes($dto));

        $fresh = $dish->fresh(['menuCategory.restaurant']) ?? $dish->load(['menuCategory.restaurant']);

        return $this->dishMapper->toRecord($fresh);
    }

    /**
     * {@inheritDoc}
     */
    public function updateImageUrl(int $dishId, string $imageUrl): DishRecord
    {
        $dish = Dish::query()->findOrFail($dishId);
        $dish->update(['image_url' => $imageUrl]);

        $fresh = $dish->fresh(['menuCategory.restaurant']) ?? $dish->load(['menuCategory.restaurant']);

        return $this->dishMapper->toRecord($fresh);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $dishId): void
    {
        Dish::query()->whereKey($dishId)->delete();
    }
}
