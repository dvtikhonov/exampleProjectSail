<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Order\FoodOrderAfterSubmitNotifierInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\Enums\Food\Order\FoodOrderAfterSubmitNotifyKind;
use App\Jobs\Food\NotifyFoodOrderAfterSubmitJob;

/**
 * Laravel-адаптер {@see FoodOrderAfterSubmitNotifierInterface}: dispatch NotifyFoodOrderAfterSubmitJob.
 */
class LaravelFoodOrderAfterSubmitNotifier implements FoodOrderAfterSubmitNotifierInterface
{
    public function __construct(
        private readonly JobDispatcherInterface $jobDispatcher,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notify(
        FoodOrderRecord $order,
        OrderDto $dto,
        int $maxUserId,
        FoodOrderAfterSubmitNotifyKind $kind,
    ): void {
        $this->jobDispatcher->dispatch(new NotifyFoodOrderAfterSubmitJob(
            orderDto: $dto,
            orderId: $order->id,
            maxUserId: $maxUserId,
            kind: $kind,
        ));
    }
}
