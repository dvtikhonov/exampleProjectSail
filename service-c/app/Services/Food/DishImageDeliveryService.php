<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageDeliveryInterface;
use App\Contracts\Food\DishRepositoryInterface;
use App\Models\Dish;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Отдача изображения блюда из локального public disk.
 */
class DishImageDeliveryService implements DishImageDeliveryInterface
{
    public function __construct(
        private readonly DishRepositoryInterface $dishRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function deliverById(int $dishId): Response
    {
        $dish = $this->dishRepository->findByIdWithTrashed($dishId);

        if ($dish === null) {
            abort(404);
        }

        return $this->deliver($dish);
    }

    /**
     * {@inheritDoc}
     */
    public function deliver(Dish $dish): Response
    {
        $source = $dish->image_url;

        if ($source === null || $source === '') {
            abort(404);
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($source)) {
            abort(404);
        }

        return Storage::disk('public')->response($source, headers: [
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }
}
