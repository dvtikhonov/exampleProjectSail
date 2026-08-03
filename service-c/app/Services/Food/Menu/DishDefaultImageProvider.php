<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

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
        $paths = $this->copyForDishes([$dishId]);

        return $paths[$dishId];
    }

    /**
     * Копирует placeholder для нескольких блюд, читая исходный файл один раз.
     *
     * @param  list<int>  $dishIds
     * @return array<int, string> dishId => relative path
     *
     * @throws FoodDomainException
     */
    public function copyForDishes(array $dishIds): array
    {
        if ($dishIds === []) {
            return [];
        }

        $sourcePath = base_path(self::SOURCE_ASSET);

        if (! is_file($sourcePath)) {
            throw new FoodDomainException('Placeholder-изображение блюда не найдено.');
        }

        $contents = File::get($sourcePath);
        $result = [];

        foreach ($dishIds as $dishId) {
            $relativePath = sprintf('dishes/%d/%s.jpg', $dishId, (string) Str::uuid());

            $stored = Storage::disk('public')->put($relativePath, $contents);

            if ($stored === false) {
                throw new FoodDomainException('Не удалось сохранить placeholder-изображение.');
            }

            $result[(int) $dishId] = $relativePath;
        }

        return $result;
    }
}
