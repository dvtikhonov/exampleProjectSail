<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\Models\Food\FoodOrder;

/**
 * Резолвер получателей клиентских push-уведомлений о заказе.
 *
 * Для обычного заказа — владелец заказа; для ручного — активные max_manager.
 */
interface OrderCustomerNotifyRecipientResolverInterface
{
    /**
     * Возвращает max_user_id получателей клиентского уведомления по заказу.
     *
     * @return list<int>
     */
    public function resolveMaxUserIds(FoodOrder $order): array;
}
