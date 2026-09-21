<?php

declare(strict_types=1);

namespace App\Services\Food\Review\UpdateStep;

use App\Contracts\Food\Review\OrderReviewUpdateStepHandlerInterface;
use App\Contracts\Food\Review\OrderStatusResolverInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\FoodOrderUpdateCommand;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Enums\Food\Review\OrderReviewStep;

/**
 * Формирует команду обновления для этапа проверки адреса.
 */
final class AddressOrderReviewUpdateStepHandler implements OrderReviewUpdateStepHandlerInterface
{
    public function __construct(
        private readonly OrderStatusResolverInterface $orderStatusResolver,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function step(): OrderReviewStep
    {
        return OrderReviewStep::Address;
    }

    /**
     * {@inheritdoc}
     */
    public function buildApprovalUpdate(
        FoodOrderRecord $order,
        int $adminId,
        string $reviewedAt,
    ): FoodOrderUpdateCommand {
        $stepStatus = OrderReviewStatus::Approved;

        return new FoodOrderUpdateCommand(
            status: $this->resolveOrderStatus($order, $stepStatus),
            addressReviewStatus: $stepStatus,
            addressReviewedBy: $adminId,
            addressReviewedAt: $reviewedAt,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function buildRejectionUpdate(
        FoodOrderRecord $order,
        int $adminId,
        string $comment,
        string $reviewedAt,
    ): FoodOrderUpdateCommand {
        $stepStatus = OrderReviewStatus::Rejected;

        return new FoodOrderUpdateCommand(
            status: $this->resolveOrderStatus($order, $stepStatus),
            addressReviewStatus: $stepStatus,
            addressReviewedBy: $adminId,
            addressReviewedAt: $reviewedAt,
            addressRejectionComment: $comment,
        );
    }

    /**
     * Итоговый статус заказа с подстановкой статуса этапа адреса.
     */
    private function resolveOrderStatus(
        FoodOrderRecord $order,
        OrderReviewStatus $stepStatus,
    ): OrderStatus {
        return $this->orderStatusResolver->resolve(
            $stepStatus,
            $order->compositionReviewStatus,
            $order->paymentReviewStatus,
        );
    }
}
