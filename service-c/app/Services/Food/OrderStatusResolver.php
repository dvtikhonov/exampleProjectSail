<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Enums\Food\OrderReviewStatus;
use App\Enums\Food\OrderStatus;
use App\Models\FoodOrder;

/**
 * Вычисляет итоговый статус заказа по статусам этапов проверки.
 */
class OrderStatusResolver
{
    public function resolve(
        OrderReviewStatus $addressReviewStatus,
        OrderReviewStatus $compositionReviewStatus,
    ): OrderStatus {
        if ($addressReviewStatus === OrderReviewStatus::Rejected
            || $compositionReviewStatus === OrderReviewStatus::Rejected) {
            return OrderStatus::Rejected;
        }

        if ($addressReviewStatus === OrderReviewStatus::Approved
            && $compositionReviewStatus === OrderReviewStatus::Approved) {
            return OrderStatus::Confirmed;
        }

        return OrderStatus::PendingReview;
    }

    public function resolveForOrder(FoodOrder $order): OrderStatus
    {
        return $this->resolve(
            $order->address_review_status,
            $order->composition_review_status,
        );
    }
}
