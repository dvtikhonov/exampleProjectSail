<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;

/**
 * Завершение проверки заказа: уведомление клиента после полного подтверждения.
 */
interface OrderReviewCompletionServiceInterface
{
    /**
     * Отправляет уведомление клиенту, если заказ впервые перешёл в статус «принят к исполнению».
     */
    public function notifyIfFullyApproved(OrderStatus $statusBefore, FoodOrderRecord $orderAfter): void;
}
