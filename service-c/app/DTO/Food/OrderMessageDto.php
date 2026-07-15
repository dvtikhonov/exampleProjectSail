<?php

declare(strict_types=1);

namespace App\DTO\Food;

use App\Enums\Food\OrderMessageAuthorType;

/**
 * Сообщение чата по заказу еды для API.
 */
readonly class OrderMessageDto
{
    public function __construct(
        public int $id,
        public int $foodOrderId,
        public int $senderMaxUserId,
        public ?string $senderFirstName,
        public ?string $senderLastName,
        public ?string $senderUsername,
        public OrderMessageAuthorType $authorType,
        public string $body,
        public string $createdAt,
    ) {}

    /**
     * Преобразует DTO сообщения заказа в массив.
     *
     * @return array<string, int|string|array<string, int|string|null>>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'food_order_id' => $this->foodOrderId,
            'sender_max_user_id' => $this->senderMaxUserId,
            'sender' => [
                'first_name' => $this->senderFirstName,
                'last_name' => $this->senderLastName,
                'username' => $this->senderUsername,
            ],
            'author_type' => $this->authorType->value,
            'body' => $this->body,
            'created_at' => $this->createdAt,
        ];
    }
}
