<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUrlResolverInterface;
use Illuminate\Support\Facades\Storage;

class DishImageUrlResolver implements DishImageUrlResolverInterface
{
    public function resolve(?string $imageUrl): ?string
    {
        if ($imageUrl === null || $imageUrl === '') {
            return null;
        }

        if (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://')) {
            return $imageUrl;
        }

        return Storage::disk('public')->url($imageUrl);
    }
}
