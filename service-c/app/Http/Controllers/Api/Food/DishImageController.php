<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Food;

use App\Contracts\Food\DishImageDeliveryInterface;
use App\Http\Controllers\Controller;
use App\Models\Dish;
use Symfony\Component\HttpFoundation\Response;

/**
 * Отдача изображения блюда через same-origin URL.
 */
class DishImageController extends Controller
{
    public function __construct(
        private readonly DishImageDeliveryInterface $dishImageDelivery,
    ) {}

    /**
     * Возвращает бинарное содержимое изображения блюда.
     */
    public function show(Dish $dish): Response
    {
        return $this->dishImageDelivery->deliver($dish);
    }
}
