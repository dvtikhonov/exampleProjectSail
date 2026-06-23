<?php

declare(strict_types=1);

namespace App\Contracts\Food;

/**
 * Формирование публичного URL изображения блюда для mini-app.
 */
interface DishImageUrlResolverInterface
{
    /**
     * Same-origin URL для img: WebView MAX не грузит внешние CDN напрямую.
     */
    public function resolvePublicUrl(int $dishId, ?string $imageUrl): ?string;
}
