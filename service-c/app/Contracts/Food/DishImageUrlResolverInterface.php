<?php

declare(strict_types=1);

namespace App\Contracts\Food;

interface DishImageUrlResolverInterface
{
    /**
     * Преобразует значение image_url из БД в публичный URL для клиента.
     */
    public function resolve(?string $imageUrl): ?string;
}
