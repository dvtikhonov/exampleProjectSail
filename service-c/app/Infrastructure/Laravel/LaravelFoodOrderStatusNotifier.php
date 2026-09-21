<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Review\FoodOrderCustomerStatusMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderManualCreatorNotifierInterface;
use App\Contracts\Food\Review\FoodOrderStatusNotifierInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Review\OrderRejectionScope;

/**
 * Уведомления клиента о статусе заявки через MAX.
 */
class LaravelFoodOrderStatusNotifier implements FoodOrderStatusNotifierInterface
{
    public function __construct(
        private readonly FoodOrderCustomerStatusMaxMessageBuilderInterface $statusMessageBuilder,
        private readonly FoodOrderCustomerMaxDispatchHelper $dispatchHelper,
        private readonly FoodOrderManualCreatorNotifierInterface $manualCreatorNotifier,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notifySubmitted(FoodOrderRecord $order): void
    {
        $text = $this->statusMessageBuilder->buildCustomerSubmitted($order);
        $buttonRows = $this->dispatchHelper->buildOpenAppButtonRows($order->id);

        $this->dispatchHelper->trySendMessage($text, $order, $buttonRows);
    }

    /**
     * {@inheritDoc}
     */
    public function notifyConfirmed(FoodOrderRecord $order): void
    {
        $text = $this->statusMessageBuilder->buildCustomerConfirmed($order);

        $this->dispatchHelper->trySendMessage($text, $order);

        $this->manualCreatorNotifier->notifyManualOrderCreatorConfirmed($order);
    }

    /**
     * {@inheritDoc}
     */
    public function notifyRejected(FoodOrderRecord $order, OrderRejectionScope $scope): void
    {
        $text = $this->statusMessageBuilder->buildCustomerRejected($order, $scope);

        $this->dispatchHelper->trySendMessage($text, $order);
    }
}
