<?php

declare(strict_types=1);

namespace App\Services\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;

/**
 * Вычисляет итоговый статус заказа по статусам этапов проверки.
 */
class OrderStatusResolver
{
    /**
     * Итоговый статус заказа по трём этапам проверки (адрес, состав, оплата).
     */
    public function resolve(
        OrderReviewStatus $addressReviewStatus,
        OrderReviewStatus $compositionReviewStatus,
        OrderReviewStatus $paymentReviewStatus,
    ): OrderStatus {
        if ($addressReviewStatus === OrderReviewStatus::Rejected
            || $compositionReviewStatus === OrderReviewStatus::Rejected
            || $paymentReviewStatus === OrderReviewStatus::Rejected) {
            return OrderStatus::Rejected;
        }

        if ($addressReviewStatus === OrderReviewStatus::Approved
            && $compositionReviewStatus === OrderReviewStatus::Approved
            && $paymentReviewStatus === OrderReviewStatus::Approved) {
            return OrderStatus::Confirmed;
        }

        return OrderStatus::PendingReview;
    }

    /**
     * Итоговый статус по полям этапов проверки модели заказа.
     */
    public function resolveForOrder(FoodOrderRecord $order): OrderStatus
    {
        return $this->resolve(
            $order->addressReviewStatus,
            $order->compositionReviewStatus,
            $order->paymentReviewStatus,
        );
    }
}
