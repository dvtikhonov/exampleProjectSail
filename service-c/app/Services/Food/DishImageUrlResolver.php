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

        return '/api/food/dishes/'.$dishId.'/image';
    }
}
