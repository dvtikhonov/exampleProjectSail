<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Снимок состава заказа с пересчитанными суммами для сохранения.
 *
 * @param  list<array{
 *     dish_id: int,
 *     dish_name: string,
 *     unit_price: string,
 *     quantity: int,
 *     line_total: string,
 *     image_url: string|null,
 *     combo_ref?: string,
 *     combo_partner_dish_ids?: list<int>
 * }>  $itemsSnapshot
 */
readonly class OrderCompositionSnapshotDto
{
    /**
     * @param  list<array<string, mixed>>  $itemsSnapshot
     */
    public function __construct(
        public array $itemsSnapshot,
        public string $itemsTotal,
        public ?string $deliveryCost,
        public string $total,
    ) {}
}
