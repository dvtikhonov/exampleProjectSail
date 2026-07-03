<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\Dish;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доставка изображения блюда клиенту mini-app.
 */
interface DishImageDeliveryInterface
{
    /**
     * Отдаёт изображение блюда по id, включая soft-deleted записи.
     */
    public function deliverById(int $dishId): Response;

    /**
     * Отдаёт изображение блюда клиенту из локального public disk.
     */
    public function deliver(Dish $dish): Response;
}
