<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\OrderMessageDto;
use App\Models\FoodOrder;

/**
 * Push-уведомления MAX о новых сообщениях в чате заказа.
 */
interface OrderChatNotifierInterface
{
    /**
     * Отправляет push получателям в зависимости от автора сообщения.
     *
     * Сообщение от клиента — всем активным админам заказов.
     * Сообщение от админа — клиенту-владельцу заказа.
     */
    public function notify(FoodOrder $order, OrderMessageDto $message): void;
}
