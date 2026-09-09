<?php

declare(strict_types=1);

namespace App\Contracts\Food\Menu;

use App\DTO\Food\Menu\DishImageFileDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Разрешение локального файла изображения блюда для mini-app.
 */
interface DishImageDeliveryInterface
{
    /**
     * Возвращает путь к изображению блюда по id, включая soft-deleted записи.
     *
     * @throws FoodDomainException если блюдо или файл недоступны (404)
     */
    public function resolveById(int $dishId): DishImageFileDto;
}
