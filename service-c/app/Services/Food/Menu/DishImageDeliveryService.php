<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\Contracts\Food\Menu\DishImageDeliveryInterface;
use App\Contracts\Shared\FileStorageInterface;
use App\DTO\Food\Menu\DishImageFileDto;
use App\DTO\Food\Menu\DishRecord;
use App\Exceptions\Food\FoodDomainException;

/**
 * Разрешение изображения блюда из локального public disk.
 */
class DishImageDeliveryService implements DishImageDeliveryInterface
{
    private const CACHE_HEADERS = [
        'Cache-Control' => 'public, max-age=86400, immutable',
    ];

    public function __construct(
        private readonly DishCatalogRepositoryInterface $dishRepository,
        private readonly FileStorageInterface $fileStorage,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolveById(int $dishId): DishImageFileDto
    {
        $dish = $this->dishRepository->findByIdWithTrashed($dishId);

        if ($dish === null) {
            throw new FoodDomainException('Изображение блюда не найдено.', 404);
        }

        return $this->resolve($dish);
    }

    /**
     * Разрешает локальный файл изображения блюда.
     *
     * @throws FoodDomainException
     */
    private function resolve(DishRecord $dish): DishImageFileDto
    {
        $source = $dish->imageUrl;

        if ($source === null || $source === '') {
            throw new FoodDomainException('Изображение блюда не найдено.', 404);
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            throw new FoodDomainException('Изображение блюда не найдено.', 404);
        }

        if (! $this->fileStorage->exists($source)) {
            throw new FoodDomainException('Изображение блюда не найдено.', 404);
        }

        return new DishImageFileDto(
            absolutePath: $this->fileStorage->path($source),
            headers: self::CACHE_HEADERS,
        );
    }
}
