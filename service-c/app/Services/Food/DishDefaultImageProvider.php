<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Exceptions\Food\FoodDomainException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Копирует placeholder-изображение блюда в публичное хранилище при импорте.
 */
class DishDefaultImageProvider
{
    private const string PLACEHOLDER_RELATIVE_PATH = 'database/seeders/assets/dishes/placeholder-1.jpg';

    /**
     * Копирует placeholder в storage/app/public/dishes/{dishId}/{uuid}.jpg.
     *
     * @throws FoodDomainException
     */
    public function copyForDish(int $dishId): string
    {
        $sourcePath = base_path(self::PLACEHOLDER_RELATIVE_PATH);

        if (! File::isFile($sourcePath)) {
            throw new FoodDomainException('Placeholder-изображение для импорта не найдено.');
        }

        $relativePath = sprintf('dishes/%d/%s.jpg', $dishId, (string) Str::uuid());
        $destinationDirectory = dirname($relativePath);

        if (! Storage::disk('public')->exists($destinationDirectory)) {
            Storage::disk('public')->makeDirectory($destinationDirectory);
        }

        $stored = Storage::disk('public')->put(
            $relativePath,
            File::get($sourcePath),
        );

        if ($stored === false) {
            throw new FoodDomainException('Не удалось сохранить изображение блюда.');
        }

        return $relativePath;
    }
}
