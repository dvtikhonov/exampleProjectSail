<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Contracts;

use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Источник confirmed-заказов для backfill строк max_food_order_items.
 */
interface FoodOrderConfirmedBackfillSourceInterface
{
    /**
     * Обходит только заказы со status = confirmed.
     *
     * @param  callable(FoodOrderRecord): void  $callback
     * @return int количество обработанных заказов
     */
    public function eachConfirmed(callable $callback, int $chunkSize = 100): int;
}
