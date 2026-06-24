<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderCustomerNotifierInterface;
use App\Enums\Food\OrderStatus;
use App\Models\FoodOrder;

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
