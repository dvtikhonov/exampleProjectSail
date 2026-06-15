<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUrlResolverInterface;

class DishImageUrlResolver implements DishImageUrlResolverInterface
{
    public function resolvePublicUrl(int $dishId, ?string $imageUrl): ?string
    {
        if ($imageUrl === null || $imageUrl === '') {
            return null;
        }

        return '/api/food/dishes/'.$dishId.'/image';
    }
}
