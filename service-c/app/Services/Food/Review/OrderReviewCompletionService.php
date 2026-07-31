<?php

declare(strict_types=1);

namespace App\Services\Food\Review;

use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Enums\Food\Order\OrderStatus;
use App\Models\Food\FoodOrder;

/**
 * Завершение проверки заказа: уведомление клиента после полного подтверждения.
 */
class OrderReviewCompletionService
{
    public function __construct(
        private readonly FoodOrderCustomerNotifierInterface $foodOrderCustomerNotifier,
    ) {}

    /**
     * Отправляет уведомление клиенту, если заказ впервые перешёл в статус «принят к исполнению».
     */
    public function notifyIfFullyApproved(OrderStatus $statusBefore, FoodOrder $orderAfter): void
    {
        if ($statusBefore === OrderStatus::Confirmed) {
            return;
        }

        if ($orderAfter->status === OrderStatus::Confirmed) {
            $this->foodOrderCustomerNotifier->notifyConfirmed($orderAfter);
        }
    }
}
