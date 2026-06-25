<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\FoodOrderRepositoryInterface;
use App\Enums\Food\OrderRejectionScope;
use App\Enums\Food\OrderReviewStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\FoodOrder;
use App\Models\MaxUser;
use Illuminate\Support\Facades\DB;

/**
 * Подтверждение получения оплаты проверяющим адреса.
 */
class OrderPaymentReviewService
{
    public function __construct(
        private readonly FoodOrderRepositoryInterface $foodOrderRepository,
        private readonly OrderReviewAuthorizationService $orderReviewAuthorizationService,
        private readonly OrderStatusResolver $orderStatusResolver,
        private readonly OrderReviewCompletionService $orderReviewCompletionService,
        private readonly FoodOrderCustomerNotifierInterface $foodOrderCustomerNotifier,
    ) {}

    /**
     * @throws FoodDomainException
     */
    public function approve(int $orderId, MaxUser $admin): FoodOrder
    {
        $statusBefore = null;

        $order = DB::transaction(function () use ($orderId, $admin, &$statusBefore): FoodOrder {
            $order = $this->findOrderForReview($orderId);
            $statusBefore = $order->status;

            $this->orderReviewAuthorizationService->assertCanApprovePayment($admin, $order);

            $paymentReviewStatus = OrderReviewStatus::Approved;

            return $this->foodOrderRepository->update($order, [
                'payment_review_status' => $paymentReviewStatus,
                'payment_reviewed_by' => $admin->max_user_id,
                'payment_reviewed_at' => now(),
                'status' => $this->orderStatusResolver->resolve(
                    $order->address_review_status,
                    $order->composition_review_status,
                    $paymentReviewStatus,
                ),
            ]);
        });

        $this->orderReviewCompletionService->notifyIfFullyApproved($statusBefore, $order);

        return $order;
    }

    /**
     * @throws FoodDomainException
     */
    public function reject(int $orderId, MaxUser $admin, string $comment): FoodOrder
    {
        $order = DB::transaction(function () use ($orderId, $admin, $comment): FoodOrder {
            $order = $this->findOrderForReview($orderId);

            $this->orderReviewAuthorizationService->assertCanRejectPayment($admin, $order, $comment);

            $paymentReviewStatus = OrderReviewStatus::Rejected;

            return $this->foodOrderRepository->update($order, [
                'payment_review_status' => $paymentReviewStatus,
                'payment_rejection_comment' => $comment,
                'payment_reviewed_by' => $admin->max_user_id,
                'payment_reviewed_at' => now(),
                'status' => $this->orderStatusResolver->resolve(
                    $order->address_review_status,
                    $order->composition_review_status,
                    $paymentReviewStatus,
                ),
            ]);
        });

        $this->foodOrderCustomerNotifier->notifyRejected($order, OrderRejectionScope::Payment);

        return $order;
    }

    /**
     * @throws FoodDomainException
     */
    private function findOrderForReview(int $orderId): FoodOrder
    {
        $order = $this->foodOrderRepository->findByIdForUpdate($orderId);

        if ($order === null) {
            throw new FoodDomainException('Order not found.', 404);
        }

        return $order;
    }
}
