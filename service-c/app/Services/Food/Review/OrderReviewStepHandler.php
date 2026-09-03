<?php

declare(strict_types=1);

namespace App\Services\Food\Review;

use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\OrderReviewStepHandlerInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Review\OrderReviewStep;
use App\Exceptions\Food\FoodDomainException;

/**
 * Единый обработчик approve/reject для всех этапов проверки заказа.
 */
class OrderReviewStepHandler implements OrderReviewStepHandlerInterface
{
    public function __construct(
        private readonly FoodOrderWriteRepositoryInterface $foodOrderWriteRepository,
        private readonly OrderReviewAuthorizationService $orderReviewAuthorizationService,
        private readonly OrderReviewUpdateFactory $orderReviewUpdateFactory,
        private readonly OrderReviewCompletionService $orderReviewCompletionService,
        private readonly FoodOrderCustomerNotifierInterface $foodOrderCustomerNotifier,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * Одобряет шаг проверки заказа.
     *
     * @throws FoodDomainException
     */
    public function approve(OrderReviewStep $step, int $orderId, MaxUserIdentity $admin): FoodOrderRecord
    {
        $statusBefore = null;

        $order = $this->transactionManager->run(function () use ($step, $orderId, $admin, &$statusBefore): FoodOrderRecord {
            $order = $this->findOrderForReview($orderId);
            $statusBefore = $order->status;

            $this->orderReviewAuthorizationService->assertCanApprove($admin, $order, $step);

            return $this->foodOrderWriteRepository->update(
                $order,
                $this->orderReviewUpdateFactory->buildApprovalUpdate($step, $order, $admin->maxUserId),
            );
        });

        $this->orderReviewCompletionService->notifyIfFullyApproved($statusBefore, $order);

        return $order;
    }

    /**
     * Отклоняет шаг проверки заказа.
     *
     * @throws FoodDomainException
     */
    public function reject(OrderReviewStep $step, int $orderId, MaxUserIdentity $admin, string $comment): FoodOrderRecord
    {
        $order = $this->transactionManager->run(function () use ($step, $orderId, $admin, $comment): FoodOrderRecord {
            $order = $this->findOrderForReview($orderId);

            $this->orderReviewAuthorizationService->assertCanReject($admin, $order, $step, $comment);

            return $this->foodOrderWriteRepository->update(
                $order,
                $this->orderReviewUpdateFactory->buildRejectionUpdate($step, $order, $admin->maxUserId, $comment),
            );
        });

        $this->foodOrderCustomerNotifier->notifyRejected($order, $step->rejectionScope());

        return $order;
    }

    /**
     * Находит заказ для проверки или выбрасывает исключение.
     *
     * @throws FoodDomainException
     */
    private function findOrderForReview(int $orderId): FoodOrderRecord
    {
        $order = $this->foodOrderWriteRepository->findByIdForUpdate($orderId);

        if ($order === null) {
            throw new FoodDomainException('Заказ не найден.', 404);
        }

        return $order;
    }
}
