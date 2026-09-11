<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Repositories;

use App\Contracts\Shared\TransactionManagerInterface;
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
     */
    public function upsertByOrderId(int $orderId, array $rows): void
    {
        $this->transactionManager->run(function () use ($orderId, $rows): void {
            if ($rows === []) {
                FoodOrderItem::query()->where('order_id', $orderId)->delete();

                return;
            }

            $now = now();
            $withDishId = [];
            $withoutDishId = [];

            foreach ($rows as $row) {
                $payload = [
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

                if ($payload['dish_id'] !== null) {
                    $withDishId[] = $payload;
                } else {
                    $withoutDishId[] = $payload;
                }
            }

            foreach (array_chunk($withDishId, 500) as $chunk) {
                FoodOrderItem::query()->upsert(
                    $chunk,
                    ['order_id', 'dish_id'],
                    [
                        'restaurant_id',
                        'report_date',
                        'dish_name',
                        'unit_price',
                        'quantity',
                        'line_total',
                        'updated_at',
                    ],
                );
            }

            foreach ($withoutDishId as $payload) {
                $existing = FoodOrderItem::query()
                    ->where('order_id', $orderId)
                    ->whereNull('dish_id')
                    ->where('dish_name', $payload['dish_name'])
                    ->first();

                if ($existing !== null) {
                    $existing->update([
                        'restaurant_id' => $payload['restaurant_id'],
                        'report_date' => $payload['report_date'],
                        'unit_price' => $payload['unit_price'],
                        'quantity' => $payload['quantity'],
                        'line_total' => $payload['line_total'],
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                FoodOrderItem::query()->create([
                    'order_id' => $orderId,
                    'restaurant_id' => $payload['restaurant_id'],
                    'report_date' => $payload['report_date'],
                    'dish_id' => null,
                    'dish_name' => $payload['dish_name'],
                    'unit_price' => $payload['unit_price'],
                    'quantity' => $payload['quantity'],
                    'line_total' => $payload['line_total'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->deleteOrphans($orderId, $withDishId, $withoutDishId);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByOrderId(int $orderId): void
    {
        FoodOrderItem::query()->where('order_id', $orderId)->delete();
    }

    /**
     * Удаляет строки заказа, которых нет во входном наборе (смена состава).
     *
     * @param  list<array<string, mixed>>  $withDishId
     * @param  list<array<string, mixed>>  $withoutDishId
     */
    private function deleteOrphans(int $orderId, array $withDishId, array $withoutDishId): void
    {
        $keepDishIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['dish_id'],
            $withDishId,
        )));
        $keepNullNames = array_values(array_unique(array_map(
            static fn (array $row): string => (string) $row['dish_name'],
            $withoutDishId,
        )));

        FoodOrderItem::query()
            ->where('order_id', $orderId)
            ->where(function ($query) use ($keepDishIds, $keepNullNames): void {
                $query->where(function ($q) use ($keepDishIds): void {
                    $q->whereNotNull('dish_id');
                    if ($keepDishIds !== []) {
                        $q->whereNotIn('dish_id', $keepDishIds);
                    }
                })->orWhere(function ($q) use ($keepNullNames): void {
                    $q->whereNull('dish_id');
                    if ($keepNullNames !== []) {
                        $q->whereNotIn('dish_name', $keepNullNames);
                    }
                });
            })
            ->delete();
    }
}
