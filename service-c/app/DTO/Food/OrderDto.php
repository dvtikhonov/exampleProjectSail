<?php

declare(strict_types=1);

namespace App\DTO\Food;

readonly class OrderDto
{
    /**
     * @param  list<array<string, mixed>>  $itemsSnapshot
     */
    public function __construct(
        public int $id,
        public string $status,
        public int $restaurantId,
        public string $restaurantName,
        public string $itemsTotal,
        public bool $deliveryApplicable,
        public ?string $deliveryCost,
        public string $total,
        public ?string $deliveryAddress,
        public array $itemsSnapshot,
        public string $createdAt,
    ) {}

    /**
     * @return array<string, bool|int|string|null|list<array<string, mixed>>>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'restaurant_id' => $this->restaurantId,
            'restaurant_name' => $this->restaurantName,
            'items_total' => $this->itemsTotal,
            'delivery_applicable' => $this->deliveryApplicable,
            'delivery_cost' => $this->deliveryCost,
            'total' => $this->total,
            'delivery_address' => $this->deliveryAddress,
            'items_snapshot' => $this->itemsSnapshot,
            'created_at' => $this->createdAt,
        ];
    }
}
