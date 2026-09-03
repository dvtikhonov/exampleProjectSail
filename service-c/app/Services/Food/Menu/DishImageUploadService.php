<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishImageUploadInterface;
use App\Contracts\Shared\FileStorageInterface;
use App\DTO\Shared\UploadedFileDto;
use App\Exceptions\Food\FoodDomainException;
use App\Support\Food\Menu\DishPhotoAllowedExtensions;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Загрузка фото блюда: whitelist PNG/JPEG, finfo MIME, минимум 800×600, до 25 МБ.
 */
class DishImageUploadService implements DishImageUploadInterface
{
    public function __construct(
        private readonly FileStorageInterface $fileStorage,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function upload(int $dishId, UploadedFileDto $file): string
    {
        $this->assertValidUpload($file);
        $this->assertAllowedExtension($file);
        $this->assertMaxSize($file);
        $this->assertAllowedMime($file);
        $this->assertMinDimensions($file);

        $extension = DishPhotoAllowedExtensions::normalizeExtension($file->extension());
        $relativePath = sprintf('dishes/%d/%s.%s', $dishId, $this->generateUniqueId(), $extension);
        $directory = dirname($relativePath);

        try {
            $this->fileStorage->makeDirectory($directory);

            $stored = $this->fileStorage->putFileAs(
                $directory,
                $file->path,
                basename($relativePath),
            );
        } catch (Throwable $exception) {
            $this->logger->error('Dish image upload failed.', [
                'dish_id' => $dishId,
                'path' => $relativePath,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new FoodDomainException(
                'Не удалось сохранить изображение. Проверьте права на storage/app/public.',
            );
        }

        if ($stored === false) {
            $this->logger->error('Dish image upload returned false (disk public, throw=false).', [
                'dish_id' => $dishId,
                'path' => $relativePath,
                'storage_root' => $this->fileStorage->path(''),
            ]);

            throw new FoodDomainException(
                'Не удалось сохранить изображение. Проверьте права на storage/app/public.',
            );
        }

        return $relativePath;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteIfExists(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
            return;
        }

        if ($this->fileStorage->exists($relativePath)) {
            $this->fileStorage->delete($relativePath);
        }
    }

    /**
     * Уникальный идентификатор файла (UUID v4).
     */
    private function generateUniqueId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    /**
     * Проверяет файл загрузки изображения блюда.
     *
     * @throws FoodDomainException
     */
    private function assertValidUpload(UploadedFileDto $file): void
    {
        if ($file->path === '' || ! is_readable($file->path)) {
            throw new FoodDomainException('Файл изображения недействителен.');
        }
    }

    /**
     * Проверяет допустимое расширение файла изображения.
     *
     * @throws FoodDomainException
     */
    private function assertAllowedExtension(UploadedFileDto $file): void
    {
        if (! DishPhotoAllowedExtensions::isAllowedExtension($file->extension())) {
            throw new FoodDomainException('Допустимы только изображения PNG или JPEG.');
        }
    }

    /**
     * Проверяет, что размер файла не превышает лимит.
     *
     * @throws FoodDomainException
     */
    private function assertMaxSize(UploadedFileDto $file): void
    {
        if ($file->size < 0) {
            throw new FoodDomainException('Файл изображения недействителен.');
        }

        if ($file->size > DishPhotoAllowedExtensions::MAX_SIZE_BYTES) {
            throw new FoodDomainException('Размер изображения не должен превышать 25 МБ.');
        }
    }

    /**
     * Проверяет MIME-тип загружаемого изображения.
     *
     * @throws FoodDomainException
     */
    private function assertAllowedMime(UploadedFileDto $file): void
    {
        $mime = DishPhotoAllowedExtensions::detectMimeFromPath($file->path);

        if ($mime === null || ! DishPhotoAllowedExtensions::isAllowedMime($mime)) {
            throw new FoodDomainException('Допустимы только изображения PNG или JPEG.');
        }
    }

    /**
     * Проверяет минимальные размеры изображения.
     *
     * @throws FoodDomainException
     */
    private function assertMinDimensions(UploadedFileDto $file): void
    {
        $dimensions = DishPhotoAllowedExtensions::readDimensionsFromPath($file->path);

        if ($dimensions === null) {
            throw new FoodDomainException('Не удалось прочитать размеры изображения.');
        }

        if (! DishPhotoAllowedExtensions::meetsMinDimensions($dimensions['width'], $dimensions['height'])) {
            throw new FoodDomainException(sprintf(
                'Изображение должно быть не менее %d×%d пикселей.',
                DishPhotoAllowedExtensions::MIN_WIDTH,
                DishPhotoAllowedExtensions::MIN_HEIGHT,
            ));
        }
    }
}
