<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Exceptions\Food\FoodDomainException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Копирует placeholder-изображение блюда в public storage.
 */
class DishDefaultImageProvider
{
    private const string SOURCE_ASSET = 'database/seeders/assets/dishes/placeholder-1.jpg';

    /**
     * Копирует placeholder в каталог блюда и возвращает относительный путь в storage.
     *
     * @throws FoodDomainException
     */
    public function copyForDish(int $dishId): string
    {
        $sourcePath = base_path(self::SOURCE_ASSET);

        if (! is_file($sourcePath)) {
            throw new FoodDomainException('Placeholder-изображение блюда не найдено.');
        }

        $relativePath = sprintf('dishes/%d/%s.jpg', $dishId, (string) Str::uuid());

        $stored = Storage::disk('public')->put($relativePath, File::get($sourcePath));

        if ($stored === false) {
            throw new FoodDomainException('Не удалось сохранить placeholder-изображение.');
        }

        return $relativePath;
    }
}
