<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Repositories;

use App\Contracts\Shared\TransactionManagerInterface;
use App\Models\Food\FoodOrder;
use App\Modules\FoodReport\Contracts\FoodOrderItemWriteRepositoryInterface;
use App\Modules\FoodReport\Models\FoodOrderItem;

/**
 * Eloquent write-репозиторий строк max_food_order_items (вариант B).
 */
final class EloquentFoodOrderItemWriteRepository implements FoodOrderItemWriteRepositoryInterface
{
    public function __construct(
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * {@inheritDoc}
     *
     * Атомарный replace: lock заказа → delete всех items → insert входного набора.
     * Избегает гонок select-then-write для строк с dish_id=null.
     */
    public function upsertByOrderId(int $orderId, array $rows): void
    {
        $this->transactionManager->run(function () use ($orderId, $rows): void {
            FoodOrder::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            FoodOrderItem::query()->where('order_id', $orderId)->delete();

            if ($rows === []) {
                return;
            }

            $now = now()->toDateTimeString();
            $payloads = [];

            foreach ($rows as $row) {
                $payloads[] = [
                    'order_id' => $orderId,
                    'restaurant_id' => (int) $row['restaurant_id'],
                    'report_date' => (string) $row['report_date'],
                    'dish_id' => $row['dish_id'] !== null ? (int) $row['dish_id'] : null,
                    'dish_name' => (string) $row['dish_name'],
                    'unit_price' => $row['unit_price'],
                    'quantity' => (int) $row['quantity'],
                    'line_total' => $row['line_total'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($payloads, 500) as $chunk) {
                FoodOrderItem::query()->insert($chunk);
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByOrderId(int $orderId): void
    {
        FoodOrderItem::query()->where('order_id', $orderId)->delete();
    }
}
