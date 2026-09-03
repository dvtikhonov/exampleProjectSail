<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\Contracts\Food\Menu\DishImageDeliveryInterface;
use App\Contracts\Shared\FileStorageInterface;
use App\DTO\Food\Menu\DishRecord;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Отдача изображения блюда из локального public disk.
 */
class DishImageDeliveryService implements DishImageDeliveryInterface
{
    public function __construct(
        private readonly DishCatalogRepositoryInterface $dishRepository,
        private readonly FileStorageInterface $fileStorage,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function deliverById(int $dishId): Response
    {
        $dish = $this->dishRepository->findByIdWithTrashed($dishId);

        if ($dish === null) {
            throw new NotFoundHttpException;
        }

        return $this->deliver($dish);
    }

    /**
     * Отдаёт изображение блюда клиенту из локального public disk.
     */
    private function deliver(DishRecord $dish): Response
    {
        $source = $dish->imageUrl;

        if ($source === null || $source === '') {
            throw new NotFoundHttpException;
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            throw new NotFoundHttpException;
        }

        if (! $this->fileStorage->exists($source)) {
            throw new NotFoundHttpException;
        }

        return new BinaryFileResponse(
            $this->fileStorage->path($source),
            200,
            ['Cache-Control' => 'public, max-age=86400, immutable'],
        );
    }
}
