<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Краткие данные заказа для списка клиента.
 */
readonly class OrderListItemDto
{
    public function __construct(
        public int $id,
        public string $status,
        public int $restaurantId,
        public string $restaurantName,
        public string $total,
        public ?string $lastMessageAt,
        public int $unreadCount,
        public string $createdAt,
    ) {}

    /**
     * Преобразует элемент списка заказов клиента в массив.
     *
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'restaurant_id' => $this->restaurantId,
            'restaurant_name' => $this->restaurantName,
            'total' => $this->total,
            'last_message_at' => $this->lastMessageAt,
            'unread_count' => $this->unreadCount,
            'created_at' => $this->createdAt,
        ];
    }
}
