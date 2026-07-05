<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Снимок позиций заказа и сумма блюд до учёта доставки.
 *
 * @param list<array{
 *     dish_id: int,
 *     dish_name: string,
 *     unit_price: string,
 *     quantity: int,
 *     line_total: string,
 *     image_url: string|null
 * }> $itemsSnapshot
 */
readonly class OrderItemsSnapshotDto
{
    public function __construct(
        public array $itemsSnapshot,
        public float $itemsTotal,
    ) {}
}
