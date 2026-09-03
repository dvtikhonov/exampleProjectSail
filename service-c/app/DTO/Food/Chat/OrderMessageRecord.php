<?php

declare(strict_types=1);

namespace App\DTO\Food\Chat;

/**
 * Доменная проекция сообщения чата заказа без Eloquent.
 */
readonly class OrderMessageRecord
{
    public function __construct(
        public int $id,
        public int $foodOrderId,
        public int $senderMaxUserId,
        public string $body,
        public string $createdAt,
        public ?string $senderFirstName = null,
        public ?string $senderLastName = null,
        public ?string $senderUsername = null,
    ) {}
}
