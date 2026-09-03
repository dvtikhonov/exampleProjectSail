<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Определяет MAX user id получателей клиентских уведомлений по заказу.
 */
interface OrderCustomerNotifyRecipientResolverInterface
{
    /**
     * Возвращает список max_user_id получателей уведомления.
     *
     * @return list<int>
     */
    public function resolveMaxUserIds(FoodOrderRecord $order): array;
}
