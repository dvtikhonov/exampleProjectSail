<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\OrderMessageDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\MaxUser;

/**
 * Чтение и отправка сообщений в чате по заказу еды.
 */
interface OrderChatServiceInterface
{
    /**
     * @return list<OrderMessageDto>
     *
     * @throws FoodDomainException
     */
    public function listMessages(
        MaxUser $actor,
        int $orderId,
        ?int $afterId = null,
        int $limit = 50,
    ): array;

    /**
     * @throws FoodDomainException
     */
    public function sendMessage(MaxUser $actor, int $orderId, string $body): OrderMessageDto;
}
