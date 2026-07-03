<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Food\DishImageUrlResolverInterface;
use App\Models\Dish;

/**
 * Ожидаемый публичный URL изображения блюда (с параметром ?v=).
 */
trait ResolvesDishImageUrl
{
    protected function expectedDishImageUrl(int $dishId, ?string $storagePath): ?string
    {
        return $this->app->make(DishImageUrlResolverInterface::class)
            ->resolvePublicUrl($dishId, $storagePath);
    }

    protected function expectedDishImageUrlForModel(Dish $dish): ?string
    {
        return $this->expectedDishImageUrl((int) $dish->id, $dish->image_url);
    }
}
