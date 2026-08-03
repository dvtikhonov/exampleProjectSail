<?php

declare(strict_types=1);

namespace App\DTO\Food\ManualOrder;

/**
 * Детальные данные ручного заказа для просмотра max_manager.
 */
readonly class ManualOrderDetailDto
{
    /**
     * @param  list<array<string, mixed>>  $itemsSnapshot
     */
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
        public ?string $deliveryDate,
        public string $itemsTotal,
        public bool $deliveryApplicable,
        public ?string $deliveryCost,
        public string $total,
        public array $itemsSnapshot,
        public string $createdAt,
        public bool $hasMessages = false,
    ) {}

    /**
     * Преобразует детальный ручной заказ в массив для JSON-ответа API.
     *
     * @return array<string, array<string, int|string|null>|bool|int|list<array<string, mixed>>|string|null>
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
            'delivery_date' => $this->deliveryDate,
            'items_total' => $this->itemsTotal,
            'delivery_applicable' => $this->deliveryApplicable,
            'delivery_cost' => $this->deliveryCost,
            'total' => $this->total,
            'items_snapshot' => $this->itemsSnapshot,
            'created_at' => $this->createdAt,
            'has_messages' => $this->hasMessages,
        ];
    }
}
