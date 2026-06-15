<?php

declare(strict_types=1);

namespace App\Contracts\Food;

interface DishImageUrlResolverInterface
{
    /**
     * Same-origin URL для <img>: mobile WebView MAX не грузит внешние CDN напрямую.
     */
    public function resolvePublicUrl(int $dishId, ?string $imageUrl): ?string;
}
