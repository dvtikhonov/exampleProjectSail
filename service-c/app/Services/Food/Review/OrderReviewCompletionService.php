<?php

declare(strict_types=1);

namespace App\Services\Food\Review;

use App\Contracts\Food\Review\FoodOrderReviewNotifierInterface;
use App\Contracts\Food\Review\OrderReviewCompletionServiceInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\FoodOrderReviewNotifyKind;

/**
 * Завершение проверки заказа: уведомление клиента после полного подтверждения.
 */
class OrderReviewCompletionService implements OrderReviewCompletionServiceInterface
{
    public function __construct(
        private readonly FoodOrderReviewNotifierInterface $foodOrderReviewNotifier,
    ) {}

    /**
     * Ставит в очередь уведомление клиенту, если заказ впервые перешёл в статус «принят к исполнению».
     */
    public function notifyIfFullyApproved(OrderStatus $statusBefore, FoodOrderRecord $orderAfter): void
    {
        if ($statusBefore === OrderStatus::Confirmed) {
            return;
        }

        if ($orderAfter->status === OrderStatus::Confirmed) {
            $this->foodOrderReviewNotifier->notify(
                orderId: $orderAfter->id,
                kind: FoodOrderReviewNotifyKind::Approved,
            );
        }
    }
}
