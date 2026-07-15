<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Enums\Food\OrderReviewStatus;
use App\Enums\Food\OrderReviewStep;
use App\Enums\Food\OrderStatus;
use App\Models\FoodOrder;

/**
 * Формирует payload для approve/reject этапа проверки заказа.
 */
class OrderReviewUpdateFactory
{
    public function __construct(
        private readonly OrderStatusResolver $orderStatusResolver,
    ) {}

    /**
     * Собирает атрибуты обновления при одобрении шага проверки.
     *
     * @return array<string, mixed>
     */
    public function buildApprovalUpdate(
        OrderReviewStep $step,
        FoodOrder $order,
        int $adminId,
    ): array {
        $stepStatus = OrderReviewStatus::Approved;

        return [
            $step->statusField() => $stepStatus,
            $step->reviewedByField() => $adminId,
            $step->reviewedAtField() => now(),
            'status' => $this->resolveOrderStatus($step, $order, $stepStatus),
        ];
    }

    /**
     * Собирает атрибуты обновления при отклонении шага проверки.
     *
     * @return array<string, mixed>
     */
    public function buildRejectionUpdate(
        OrderReviewStep $step,
        FoodOrder $order,
        int $adminId,
        string $comment,
    ): array {
        $stepStatus = OrderReviewStatus::Rejected;

        return [
            $step->statusField() => $stepStatus,
            $step->rejectionCommentField() => $comment,
            $step->reviewedByField() => $adminId,
            $step->reviewedAtField() => now(),
            'status' => $this->resolveOrderStatus($step, $order, $stepStatus),
        ];
    }

    /**
     * Определяет итоговый статус заказа после проверки.
     */
    private function resolveOrderStatus(
        OrderReviewStep $step,
        FoodOrder $order,
        OrderReviewStatus $stepStatus,
    ): OrderStatus {
        return match ($step) {
            OrderReviewStep::Address => $this->orderStatusResolver->resolve(
                $stepStatus,
                $order->composition_review_status,
                $order->payment_review_status,
            ),
            OrderReviewStep::Composition => $this->orderStatusResolver->resolve(
                $order->address_review_status,
                $stepStatus,
                $order->payment_review_status,
            ),
            OrderReviewStep::Payment => $this->orderStatusResolver->resolve(
                $order->address_review_status,
                $order->composition_review_status,
                $stepStatus,
            ),
        };
    }
}
