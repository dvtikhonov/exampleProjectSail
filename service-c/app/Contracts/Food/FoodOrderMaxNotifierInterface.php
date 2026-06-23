<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\OrderDto;
use App\Models\MaxUser;

/**
 * Отправка уведомлений о новом заказе еды в MAX.
 */
interface FoodOrderMaxNotifierInterface
{
    /**
     * Уведомляет получателей о созданном заказе.
     */
    public function notify(OrderDto $order, MaxUser $maxUser): void;
}
