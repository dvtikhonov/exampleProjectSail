<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;

/**
 * Вычисляет итоговый статус заказа по статусам этапов проверки.
 */
interface OrderStatusResolverInterface
{
    /**
     * Итоговый статус заказа по трём этапам проверки (адрес, состав, оплата).
     */
    public function resolve(
        OrderReviewStatus $addressReviewStatus,
        OrderReviewStatus $compositionReviewStatus,
        OrderReviewStatus $paymentReviewStatus,
    ): OrderStatus;

    /**
     * Итоговый статус по полям этапов проверки модели заказа.
     */
    public function resolveForOrder(FoodOrderRecord $order): OrderStatus;
}
