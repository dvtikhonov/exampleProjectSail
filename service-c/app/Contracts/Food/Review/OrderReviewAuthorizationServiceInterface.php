<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Review\OrderReviewStep;
use App\Exceptions\Food\FoodDomainException;

/**
 * Проверка прав администратора и допустимости перехода статуса проверки заказа.
 */
interface OrderReviewAuthorizationServiceInterface
{
    /**
     * Проверяет право администратора одобрить шаг.
     *
     * @throws FoodDomainException
     */
    public function assertCanApprove(MaxUserIdentity $admin, FoodOrderRecord $order, OrderReviewStep $step): void;

    /**
     * Проверяет право администратора отклонить шаг.
     *
     * @throws FoodDomainException
     */
    public function assertCanReject(MaxUserIdentity $admin, FoodOrderRecord $order, OrderReviewStep $step, string $comment): void;

    /**
     * Проверяет право администратора редактировать состав заказа в очереди проверки.
     *
     * @throws FoodDomainException
     */
    public function assertCanEditComposition(MaxUserIdentity $admin, FoodOrderRecord $order): void;
}
