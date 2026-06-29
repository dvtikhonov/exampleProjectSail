<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\DishPhotoAllowedExtensions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Проверяет минимальное разрешение загруженного изображения.
 */
class MinImageDimensions implements ValidationRule
{
    public function __construct(
        private readonly int $minWidth = DishPhotoAllowedExtensions::MIN_WIDTH,
        private readonly int $minHeight = DishPhotoAllowedExtensions::MIN_HEIGHT,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Файл изображения недействителен.');

            return;
        }

        $dimensions = DishPhotoAllowedExtensions::readDimensions($value);

        if ($dimensions === null) {
            $fail('Не удалось прочитать размеры изображения.');

            return;
        }

        if (! DishPhotoAllowedExtensions::meetsMinDimensions($dimensions['width'], $dimensions['height'])) {
            $fail(sprintf(
                'Изображение должно быть не менее %d×%d пикселей.',
                $this->minWidth,
                $this->minHeight,
            ));
        }
    }
}
