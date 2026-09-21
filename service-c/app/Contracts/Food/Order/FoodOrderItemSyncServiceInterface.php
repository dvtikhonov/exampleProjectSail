<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Синхронизация max_food_order_items только для confirmed-заказов (вариант B).
 *
 * Реализация живёт в модуле FoodReport; ядро Food зависит только от этого порта.
 */
interface FoodOrderItemSyncServiceInterface
{
    /**
     * Если заказ Confirmed — upsert rows из items_snapshot; иначе deleteByOrderId.
     */
    public function syncIfConfirmed(FoodOrderRecord $order): void;
}
