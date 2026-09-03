<?php

declare(strict_types=1);

namespace App\Services\Food\Review;

use App\Contracts\Shared\ClockInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\FoodOrderUpdateCommand;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Enums\Food\Review\OrderReviewStep;
use DateTimeInterface;

/**
 * Формирует команду обновления для approve/reject этапа проверки заказа.
 */
class OrderReviewUpdateFactory
{
    public function __construct(
        private readonly OrderStatusResolver $orderStatusResolver,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Собирает команду обновления при одобрении шага проверки.
     */
    public function buildApprovalUpdate(
        OrderReviewStep $step,
        FoodOrderRecord $order,
        int $adminId,
    ): FoodOrderUpdateCommand {
        $stepStatus = OrderReviewStatus::Approved;
        $reviewedAt = $this->clock->now()->format(DateTimeInterface::ATOM);
        $resolvedStatus = $this->resolveOrderStatus($step, $order, $stepStatus);

        return match ($step) {
            OrderReviewStep::Address => new FoodOrderUpdateCommand(
                status: $resolvedStatus,
                addressReviewStatus: $stepStatus,
                addressReviewedBy: $adminId,
                addressReviewedAt: $reviewedAt,
            ),
            OrderReviewStep::Composition => new FoodOrderUpdateCommand(
                status: $resolvedStatus,
                compositionReviewStatus: $stepStatus,
                compositionReviewedBy: $adminId,
                compositionReviewedAt: $reviewedAt,
            ),
            OrderReviewStep::Payment => new FoodOrderUpdateCommand(
                status: $resolvedStatus,
                paymentReviewStatus: $stepStatus,
                paymentReviewedBy: $adminId,
                paymentReviewedAt: $reviewedAt,
            ),
        };
    }

    /**
     * Собирает команду обновления при отклонении шага проверки.
     */
    public function buildRejectionUpdate(
        OrderReviewStep $step,
        FoodOrderRecord $order,
        int $adminId,
        string $comment,
    ): FoodOrderUpdateCommand {
        $stepStatus = OrderReviewStatus::Rejected;
        $reviewedAt = $this->clock->now()->format(DateTimeInterface::ATOM);
        $resolvedStatus = $this->resolveOrderStatus($step, $order, $stepStatus);

        return match ($step) {
            OrderReviewStep::Address => new FoodOrderUpdateCommand(
                status: $resolvedStatus,
                addressReviewStatus: $stepStatus,
                addressReviewedBy: $adminId,
                addressReviewedAt: $reviewedAt,
                addressRejectionComment: $comment,
            ),
            OrderReviewStep::Composition => new FoodOrderUpdateCommand(
                status: $resolvedStatus,
                compositionReviewStatus: $stepStatus,
                compositionReviewedBy: $adminId,
                compositionReviewedAt: $reviewedAt,
                compositionRejectionComment: $comment,
            ),
            OrderReviewStep::Payment => new FoodOrderUpdateCommand(
                status: $resolvedStatus,
                paymentReviewStatus: $stepStatus,
                paymentReviewedBy: $adminId,
                paymentReviewedAt: $reviewedAt,
                paymentRejectionComment: $comment,
            ),
        };
    }

    /**
     * Определяет итоговый статус заказа после проверки.
     */
    private function resolveOrderStatus(
        OrderReviewStep $step,
        FoodOrderRecord $order,
        OrderReviewStatus $stepStatus,
    ): OrderStatus {
        return match ($step) {
            OrderReviewStep::Address => $this->orderStatusResolver->resolve(
                $stepStatus,
                $order->compositionReviewStatus,
                $order->paymentReviewStatus,
            ),
            OrderReviewStep::Composition => $this->orderStatusResolver->resolve(
                $order->addressReviewStatus,
                $stepStatus,
                $order->paymentReviewStatus,
            ),
            OrderReviewStep::Payment => $this->orderStatusResolver->resolve(
                $order->addressReviewStatus,
                $order->compositionReviewStatus,
                $stepStatus,
            ),
        };
    }
}
