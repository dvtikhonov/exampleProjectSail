<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Уведомления клиента об изменении состава заказа.
 */
interface FoodOrderCompositionNotifierInterface
{
    /**
     * Уведомляет клиента об изменении состава заказа.
     */
    public function notifyCompositionChanged(FoodOrderRecord $order): void;
}
