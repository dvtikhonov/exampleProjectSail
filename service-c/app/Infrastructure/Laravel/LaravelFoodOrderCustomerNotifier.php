<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Review\FoodOrderCompositionNotifierInterface;
use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\FoodOrderManualCreatorNotifierInterface;
use App\Contracts\Food\Review\FoodOrderStatusNotifierInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Review\OrderRejectionScope;

/**
 * Composition-адаптер полного порта клиентских MAX-уведомлений о заказе:
 * делегирует в status / composition / manual-creator.
 */
class LaravelFoodOrderCustomerNotifier implements FoodOrderCustomerNotifierInterface
{
    public function __construct(
        private readonly FoodOrderStatusNotifierInterface $statusNotifier,
        private readonly FoodOrderCompositionNotifierInterface $compositionNotifier,
        private readonly FoodOrderManualCreatorNotifierInterface $manualCreatorNotifier,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notifySubmitted(FoodOrderRecord $order): void
    {
        $this->statusNotifier->notifySubmitted($order);
    }

    /**
     * {@inheritDoc}
     */
    public function notifyConfirmed(FoodOrderRecord $order): void
    {
        $this->statusNotifier->notifyConfirmed($order);
    }

    /**
     * {@inheritDoc}
     */
    public function notifyRejected(FoodOrderRecord $order, OrderRejectionScope $scope): void
    {
        $this->statusNotifier->notifyRejected($order, $scope);
    }

    /**
     * {@inheritDoc}
     */
    public function notifyCompositionChanged(FoodOrderRecord $order): void
    {
        $this->compositionNotifier->notifyCompositionChanged($order);
    }

    /**
     * {@inheritDoc}
     */
    public function notifyManualOrderCreatorConfirmed(FoodOrderRecord $order): void
    {
        $this->manualCreatorNotifier->notifyManualOrderCreatorConfirmed($order);
    }
}
