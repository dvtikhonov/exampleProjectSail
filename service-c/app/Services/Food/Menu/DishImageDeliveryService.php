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

        $this->assertSafeRelativePath($source);

        if (! $this->fileStorage->exists($source)) {
            throw new FoodDomainException('Изображение блюда не найдено.', 404);
        }

        $absolutePath = $this->fileStorage->path($source);
        $this->assertWithinPublicDisk($absolutePath);

        return new DishImageFileDto(
            absolutePath: $absolutePath,
            headers: self::CACHE_HEADERS,
        );
    }

    /**
     * Отклоняет traversal, абсолютные пути и пути вне allow-list префикса dishes/.
     *
     * @throws FoodDomainException
     */
    private function assertSafeRelativePath(string $source): void
    {
        if (
            str_contains($source, '..')
            || str_contains($source, "\0")
            || str_starts_with($source, '/')
            || str_contains($source, '\\')
        ) {
            throw new FoodDomainException('Изображение блюда не найдено.', 404);
        }

        if (! str_starts_with($source, 'dishes/')) {
            throw new FoodDomainException('Изображение блюда не найдено.', 404);
        }
    }

    /**
     * Проверяет, что realpath файла лежит внутри корня public-диска.
     *
     * @throws FoodDomainException
     */
    private function assertWithinPublicDisk(string $absolutePath): void
    {
        $diskRoot = $this->fileStorage->path('');
        $fileReal = realpath($absolutePath);
        $rootReal = realpath($diskRoot);

        if ($fileReal === false || $rootReal === false) {
            throw new FoodDomainException('Изображение блюда не найдено.', 404);
        }

        $rootPrefix = rtrim($rootReal, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($fileReal !== $rootReal && ! str_starts_with($fileReal, $rootPrefix)) {
            throw new FoodDomainException('Изображение блюда не найдено.', 404);
        }
    }
}
