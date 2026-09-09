<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishAdminPhotoCoordinatorInterface;
use App\Contracts\Food\Menu\DishImageUploadInterface;
use App\DTO\Shared\UploadedFileDto;

/**
 * Оркестрация загрузки и удаления фото блюда в админке.
 */
class DishAdminPhotoCoordinator implements DishAdminPhotoCoordinatorInterface
{
    public function __construct(
        private readonly DishImageUploadInterface $dishImageUpload,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function assignUploadedPhoto(int $dishId, UploadedFileDto $photo): string
    {
        return $this->dishImageUpload->upload($dishId, $photo);
    }

    /**
     * {@inheritdoc}
     */
    public function deletePhotoIfExists(?string $relativePath): void
    {
        $this->dishImageUpload->deleteIfExists($relativePath);
    }
}
