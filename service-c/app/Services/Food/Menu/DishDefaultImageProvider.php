<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Shared\FileStorageInterface;
use App\Exceptions\Food\FoodDomainException;

/**
 * Копирует placeholder-изображение блюда в public storage.
 */
class DishDefaultImageProvider
{
    private const string SOURCE_ASSET = 'database/seeders/assets/dishes/placeholder-1.jpg';

    public function __construct(
        private readonly FileStorageInterface $fileStorage,
        private readonly string $basePath,
    ) {}

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

        $sourcePath = rtrim($this->basePath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, self::SOURCE_ASSET);

        if (! is_file($sourcePath)) {
            throw new FoodDomainException('Placeholder-изображение блюда не найдено.');
        }

        $contents = file_get_contents($sourcePath);

        if ($contents === false) {
            throw new FoodDomainException('Не удалось прочитать placeholder-изображение.');
        }

        $result = [];

        foreach ($dishIds as $dishId) {
            $relativePath = sprintf('dishes/%d/%s.jpg', $dishId, $this->generateUniqueId());

            $stored = $this->fileStorage->put($relativePath, $contents);

            if ($stored === false) {
                throw new FoodDomainException('Не удалось сохранить placeholder-изображение.');
            }

            $result[(int) $dishId] = $relativePath;
        }

        return $result;
    }

    /**
     * Уникальный идентификатор файла (UUID v4).
     */
    private function generateUniqueId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
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
}
