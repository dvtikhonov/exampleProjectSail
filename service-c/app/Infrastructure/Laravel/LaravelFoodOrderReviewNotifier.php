<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Review\FoodOrderReviewNotifierInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Enums\Food\Review\FoodOrderReviewNotifyKind;
use App\Enums\Food\Review\OrderRejectionScope;
use App\Jobs\Food\NotifyFoodOrderReviewJob;

/**
 * Laravel-адаптер {@see FoodOrderReviewNotifierInterface}: dispatch NotifyFoodOrderReviewJob.
 */
class LaravelFoodOrderReviewNotifier implements FoodOrderReviewNotifierInterface
{
    public function __construct(
        private readonly JobDispatcherInterface $jobDispatcher,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(
        int $orderId,
        FoodOrderReviewNotifyKind $kind,
        ?OrderRejectionScope $rejectionScope = null,
        ?string $idempotencySuffix = null,
    ): void {
        $this->jobDispatcher->dispatch(new NotifyFoodOrderReviewJob(
            orderId: $orderId,
            kind: $kind,
            rejectionScope: $rejectionScope,
            idempotencySuffix: $idempotencySuffix,
        ));
    }
}
