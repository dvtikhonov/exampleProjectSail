<?php

declare(strict_types=1);

namespace App\Services\Food\Review;

use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;

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
    public function notifyIfFullyApproved(OrderStatus $statusBefore, FoodOrderRecord $orderAfter): void
    {
        if ($statusBefore === OrderStatus::Confirmed) {
            return;
        }

        if ($orderAfter->status === OrderStatus::Confirmed) {
            $this->foodOrderCustomerNotifier->notifyConfirmed($orderAfter);
        }
    }
}
