<?php

declare(strict_types=1);

namespace App\DTO\Food\Cart;

use App\DTO\Food\Delivery\CustomerCategoryDto;

/**
 * Состояние корзины пользователя: позиции, суммы и адрес доставки.
 */
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
        public ?string $deliveryDate,
        public ?CustomerCategoryDto $customerCategory,
        public bool $deliveryApplicable,
        public ?string $nextTierMinTotal = null,
        public ?string $nextTierDeliveryCost = null,
        public ?string $amountToNextTier = null,
    ) {}

    /**
     * Копия DTO с явной датой доставки (Y-m-d или null).
     */
    public function withDeliveryDate(?string $deliveryDate): self
    {
        return new self(
            id: $this->id,
            restaurantId: $this->restaurantId,
            restaurantName: $this->restaurantName,
            status: $this->status,
            items: $this->items,
            itemsTotal: $this->itemsTotal,
            deliveryCost: $this->deliveryCost,
            total: $this->total,
            deliveryAddress: $this->deliveryAddress,
            deliveryDate: $deliveryDate,
            customerCategory: $this->customerCategory,
            deliveryApplicable: $this->deliveryApplicable,
            nextTierMinTotal: $this->nextTierMinTotal,
            nextTierDeliveryCost: $this->nextTierDeliveryCost,
            amountToNextTier: $this->amountToNextTier,
        );
    }

    /**
     * Преобразует корзину в массив для JSON-ответа API.
     *
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
            'delivery_date' => $this->deliveryDate,
            'customer_category' => $this->customerCategory?->toArray(),
            'delivery_applicable' => $this->deliveryApplicable,
            'next_tier_min_total' => $this->nextTierMinTotal,
            'next_tier_delivery_cost' => $this->nextTierDeliveryCost,
            'amount_to_next_tier' => $this->amountToNextTier,
        ];
    }
}
