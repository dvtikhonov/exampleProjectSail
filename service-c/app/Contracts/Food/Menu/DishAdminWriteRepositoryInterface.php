<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\CreateDishDto;
use App\DTO\Food\Menu\DishRecord;
use App\DTO\Food\Menu\UpdateDishDto;

/**
 * Запись блюд для административного CRUD.
 */
interface DishAdminWriteRepositoryInterface
{
    /**
     * Создаёт блюдо (image_url = null до отдельного assign фото).
     */
    public function create(CreateDishDto $dto): DishRecord;

    /**
     * Обновляет поля блюда по идентификатору (без image_url).
     */
    public function update(int $dishId, UpdateDishDto $dto): DishRecord;

    /**
     * Обновляет только путь изображения блюда.
     */
    public function updateImageUrl(int $dishId, string $imageUrl): DishRecord;

    /**
     * Удаляет блюдо по идентификатору.
     */
    public function delete(int $dishId): void;
}
