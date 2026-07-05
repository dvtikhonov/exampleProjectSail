<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\FoodOrderWriteRepositoryInterface;
use App\Enums\Food\OrderReviewStep;
use App\Exceptions\Food\FoodDomainException;
use App\Models\FoodOrder;
use App\Models\MaxUser;
use Illuminate\Support\Facades\DB;

/**
 * Единый обработчик approve/reject для всех этапов проверки заказа.
 */
class OrderReviewStepHandler
{
    public function __construct(
        private readonly FoodOrderWriteRepositoryInterface $foodOrderWriteRepository,
        private readonly OrderReviewAuthorizationService $orderReviewAuthorizationService,
        private readonly OrderReviewUpdateFactory $orderReviewUpdateFactory,
        private readonly OrderReviewCompletionService $orderReviewCompletionService,
        private readonly FoodOrderCustomerNotifierInterface $foodOrderCustomerNotifier,
    ) {}

    /**
     * @throws FoodDomainException
     */
    public function approve(OrderReviewStep $step, int $orderId, MaxUser $admin): FoodOrder
    {
        $statusBefore = null;

        $order = DB::transaction(function () use ($step, $orderId, $admin, &$statusBefore): FoodOrder {
            $order = $this->findOrderForReview($orderId);
            $statusBefore = $order->status;

            $this->orderReviewAuthorizationService->assertCanApprove($admin, $order, $step);

            return $this->foodOrderWriteRepository->update(
                $order,
                $this->orderReviewUpdateFactory->buildApprovalUpdate($step, $order, $admin->max_user_id),
            );
        });

        $this->orderReviewCompletionService->notifyIfFullyApproved($statusBefore, $order);

        return $order;
    }

    /**
     * @throws FoodDomainException
     */
    public function reject(OrderReviewStep $step, int $orderId, MaxUser $admin, string $comment): FoodOrder
    {
        $order = DB::transaction(function () use ($step, $orderId, $admin, $comment): FoodOrder {
            $order = $this->findOrderForReview($orderId);

            $this->orderReviewAuthorizationService->assertCanReject($admin, $order, $step, $comment);

            return $this->foodOrderWriteRepository->update(
                $order,
                $this->orderReviewUpdateFactory->buildRejectionUpdate($step, $order, $admin->max_user_id, $comment),
            );
        });

        $this->foodOrderCustomerNotifier->notifyRejected($order, $step->rejectionScope());

        return $order;
    }

    /**
     * @throws FoodDomainException
     */
    private function findOrderForReview(int $orderId): FoodOrder
    {
        $order = $this->foodOrderWriteRepository->findByIdForUpdate($orderId);

        if ($order === null) {
            throw new FoodDomainException('Order not found.', 404);
        }

        return $order;
    }
}
