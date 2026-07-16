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
     * Отправляет push о новом сообщении в чате заказа.
     *
     * Всегда — в MAX_UI_STAND_* (текст + тело сообщения).
     * Сообщение от админа — дополнительно клиенту-владельцу (без тела сообщения).
     * Сообщение клиента клиенту не дублируется.
     */
    public function notify(FoodOrder $order, OrderMessageDto $message): void;
}
