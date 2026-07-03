<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUrlResolverInterface;

/**
 * Формирование same-origin URL изображений блюд для mini-app.
 */
class DishImageUrlResolver implements DishImageUrlResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public function resolvePublicUrl(int $dishId, ?string $imageUrl): ?string
    {
        if ($imageUrl === null || $imageUrl === '') {
            return null;
        }

        return sprintf(
            '/api/food/dishes/%d/image?v=%s',
            $dishId,
            $this->storageVersion($imageUrl),
        );
    }

    /**
     * Короткий хеш пути в storage: меняется только при замене файла, сбрасывает кеш <img>.
     */
    private function storageVersion(string $storagePath): string
    {
        return substr(hash('sha256', $storagePath), 0, 12);
    }
}
