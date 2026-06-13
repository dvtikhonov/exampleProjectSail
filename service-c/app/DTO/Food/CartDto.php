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
        public string $itemsTotal,
        public ?string $deliveryCost,
        public string $total,
        public ?string $deliveryAddress,
        public ?CustomerCategoryDto $customerCategory,
        public bool $deliveryApplicable,
    ) {}

    /**
     * @return array<string, bool|int|string|null|list<array<string, int|string>>|array<string, int|string>>
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
            'items_total' => $this->itemsTotal,
            'delivery_cost' => $this->deliveryCost,
            'total' => $this->total,
            'delivery_address' => $this->deliveryAddress,
            'customer_category' => $this->customerCategory?->toArray(),
            'delivery_applicable' => $this->deliveryApplicable,
        ];
    }
}
