<?php

declare(strict_types=1);

namespace App\DTO\Food;

readonly class CartDto
{
    /**
     * @param  list<CartItemDto>  $items
     */
    public function __construct(
        public int $id,
        public int $restaurantId,
        public string $restaurantName,
        public string $status,
        public array $items,
        public string $total,
    ) {}

    /**
     * @return array<string, int|string|list<array<string, int|string>>>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurantId,
            'restaurant_name' => $this->restaurantName,
            'status' => $this->status,
            'items' => array_map(
                static fn (CartItemDto $item): array => $item->toArray(),
                $this->items,
            ),
            'total' => $this->total,
        ];
    }
}
