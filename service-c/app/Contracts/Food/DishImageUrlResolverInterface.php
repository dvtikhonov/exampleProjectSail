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
     * Содержит ?v= — хеш пути в storage; меняется при замене файла и сбрасывает кеш браузера.
     */
    public function resolvePublicUrl(int $dishId, ?string $imageUrl): ?string;
}
