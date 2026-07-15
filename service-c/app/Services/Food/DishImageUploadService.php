<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUploadInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Support\DishPhotoAllowedExtensions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Загрузка фото блюда: whitelist PNG/JPEG, finfo MIME, минимум 800×600, до 25 МБ.
 */
class DishImageUploadService implements DishImageUploadInterface
{
    /**
     * {@inheritDoc}
     */
    public function upload(int $dishId, UploadedFile $file): string
    {
        $this->assertValidUpload($file);
        $this->assertAllowedExtension($file);
        $this->assertMaxSize($file);
        $this->assertAllowedMime($file);
        $this->assertMinDimensions($file);

        $extension = DishPhotoAllowedExtensions::normalizeExtension(
            (string) $file->getClientOriginalExtension(),
        );
        $relativePath = sprintf('dishes/%d/%s.%s', $dishId, (string) Str::uuid(), $extension);

        $stored = Storage::disk('public')->putFileAs(
            dirname($relativePath),
            $file,
            basename($relativePath),
        );

        if ($stored === false) {
            throw new FoodDomainException('Не удалось сохранить изображение.');
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

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }

    /**
     * Проверяет файл загрузки изображения блюда.
     *
     * @throws FoodDomainException
     */
    private function assertValidUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new FoodDomainException('Файл изображения недействителен.');
        }
    }

    /**
     * Проверяет допустимое расширение файла изображения.
     *
     * @throws FoodDomainException
     */
    private function assertAllowedExtension(UploadedFile $file): void
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! DishPhotoAllowedExtensions::isAllowedExtension($extension)) {
            throw new FoodDomainException('Допустимы только изображения PNG или JPEG.');
        }
    }

    /**
     * Проверяет, что размер файла не превышает лимит.
     *
     * @throws FoodDomainException
     */
    private function assertMaxSize(UploadedFile $file): void
    {
        if ($file->getSize() > DishPhotoAllowedExtensions::MAX_SIZE_BYTES) {
            throw new FoodDomainException('Размер изображения не должен превышать 25 МБ.');
        }
    }

    /**
     * Проверяет MIME-тип загружаемого изображения.
     *
     * @throws FoodDomainException
     */
    private function assertAllowedMime(UploadedFile $file): void
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new FoodDomainException('Файл изображения недействителен.');
        }

        $mime = DishPhotoAllowedExtensions::detectMimeFromPath($path);

        if ($mime === null || ! DishPhotoAllowedExtensions::isAllowedMime($mime)) {
            throw new FoodDomainException('Допустимы только изображения PNG или JPEG.');
        }
    }

    /**
     * Проверяет минимальные размеры изображения.
     *
     * @throws FoodDomainException
     */
    private function assertMinDimensions(UploadedFile $file): void
    {
        $dimensions = DishPhotoAllowedExtensions::readDimensions($file);

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
