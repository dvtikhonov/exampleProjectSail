<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\DishPhotoAllowedExtensions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Проверяет фактический MIME-тип изображения через finfo (не доверяет расширению).
 */
class ValidDishPhotoMime implements ValidationRule
{
    /**
     * {@inheritDoc}
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Файл изображения недействителен.');

            return;
        }

        $path = $value->getRealPath();

        if ($path === false) {
            $fail('Файл изображения недействителен.');

            return;
        }

        $mime = DishPhotoAllowedExtensions::detectMimeFromPath($path);

        if ($mime === null || ! DishPhotoAllowedExtensions::isAllowedMime($mime)) {
            $fail('Допустимы только изображения PNG или JPEG.');
        }
    }
}
