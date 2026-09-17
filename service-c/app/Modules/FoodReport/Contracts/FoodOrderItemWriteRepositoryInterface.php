<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Contracts;

/**
 * Запись нормализованных строк позиций заказа в max_food_order_items.
 */
interface FoodOrderItemWriteRepositoryInterface
{
    /**
     * Атомарно заменяет все строки позиций заказа (lock + delete + insert).
     * Пустой `$rows` удаляет все items заказа.
     *
     * @param  list<array{
     *     restaurant_id: int,
     *     report_date: string,
     *     dish_id: int|null,
     *     dish_name: string,
     *     unit_price: string|float|int,
     *     quantity: int,
     *     line_total: string|float|int
     * }>  $rows
     */
    public function upsertByOrderId(int $orderId, array $rows): void;

    /**
     * Удаляет все строки позиций заказа (не-confirmed / rejected).
     */
    public function deleteByOrderId(int $orderId): void;
}
