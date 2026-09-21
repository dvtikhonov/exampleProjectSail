<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Уведомления менеджеру, оформившему ручной заказ.
 */
interface FoodOrderManualCreatorNotifierInterface
{
    /**
     * Уведомляет менеджера, оформившего ручной заказ, о подтверждении.
     */
    public function notifyManualOrderCreatorConfirmed(FoodOrderRecord $order): void;
}
