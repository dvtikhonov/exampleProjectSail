<?php

declare(strict_types=1);

namespace App\Contracts\Food\Chat;

use App\DTO\Food\Chat\OrderMessageDto;
use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Уведомление участников чата заказа о новом сообщении через MAX.
 */
interface OrderChatNotifierInterface
{
    /**
     * Отправляет MAX-уведомления о новом сообщении в чате заказа.
     */
    public function notify(FoodOrderRecord $order, OrderMessageDto $message): void;
}
