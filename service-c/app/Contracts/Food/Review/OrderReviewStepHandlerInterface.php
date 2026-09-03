<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Review\OrderReviewStep;
use App\Exceptions\Food\FoodDomainException;

/**
 * Единый обработчик approve/reject для всех этапов проверки заказа.
 */
interface OrderReviewStepHandlerInterface
{
    /**
     * Одобряет шаг проверки заказа.
     *
     * @throws FoodDomainException
     */
    public function approve(OrderReviewStep $step, int $orderId, MaxUserIdentity $admin): FoodOrderRecord;

    /**
     * Отклоняет шаг проверки заказа.
     *
     * @throws FoodDomainException
     */
    public function reject(OrderReviewStep $step, int $orderId, MaxUserIdentity $admin, string $comment): FoodOrderRecord;
}
