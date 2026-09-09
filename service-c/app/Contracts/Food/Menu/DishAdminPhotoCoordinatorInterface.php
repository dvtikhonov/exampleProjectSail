<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Shared\UploadedFileDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Оркестрация фото блюда при административном create/update.
 */
interface DishAdminPhotoCoordinatorInterface
{
    /**
     * Сохраняет загруженное фото и возвращает относительный путь в storage.
     *
     * @throws FoodDomainException
     */
    public function assignUploadedPhoto(int $dishId, UploadedFileDto $photo): string;

    /**
     * Удаляет предыдущий файл фото, если путь задан и файл существует.
     */
    public function deletePhotoIfExists(?string $relativePath): void;
}
