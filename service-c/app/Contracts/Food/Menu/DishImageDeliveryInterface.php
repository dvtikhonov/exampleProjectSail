<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

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
}
