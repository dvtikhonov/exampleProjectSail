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
        public string $total,
        public array $itemsSnapshot,
        public string $createdAt,
    ) {}

    /**
     * @return array<string, int|string|list<array<string, mixed>>>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'restaurant_id' => $this->restaurantId,
            'restaurant_name' => $this->restaurantName,
            'total' => $this->total,
            'items_snapshot' => $this->itemsSnapshot,
            'created_at' => $this->createdAt,
        ];
    }
}
