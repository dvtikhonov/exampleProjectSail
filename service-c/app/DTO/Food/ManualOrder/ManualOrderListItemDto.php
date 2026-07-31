<?php

declare(strict_types=1);

namespace App\DTO\Food\ManualOrder;

/**
 * Краткие данные ручного заказа для списка max_manager.
 */
readonly class ManualOrderListItemDto
{
    public function __construct(
        public int $id,
        public string $status,
        public int $restaurantId,
        public string $restaurantName,
        public int $customerMaxUserId,
        public ?string $customerFirstName,
        public ?string $customerLastName,
        public ?string $customerUsername,
        public ?string $deliveryAddress,
        public string $itemsTotal,
        public ?string $deliveryCost,
        public string $total,
        public string $createdAt,
    ) {}

    /**
     * Преобразует элемент списка ручных заказов в массив.
     *
     * @return array<string, array<string, int|string|null>|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'restaurant_id' => $this->restaurantId,
            'restaurant_name' => $this->restaurantName,
            'customer' => [
                'max_user_id' => $this->customerMaxUserId,
                'first_name' => $this->customerFirstName,
                'last_name' => $this->customerLastName,
                'username' => $this->customerUsername,
            ],
            'delivery_address' => $this->deliveryAddress,
            'items_total' => $this->itemsTotal,
            'delivery_cost' => $this->deliveryCost,
            'total' => $this->total,
            'created_at' => $this->createdAt,
        ];
    }
}
