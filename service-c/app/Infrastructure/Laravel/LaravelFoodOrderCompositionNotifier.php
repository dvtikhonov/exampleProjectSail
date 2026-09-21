<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Review\FoodOrderCompositionNotifierInterface;
use App\Contracts\Food\Review\FoodOrderCustomerCompositionMaxMessageBuilderInterface;
use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Уведомления клиента об изменении состава заказа через MAX.
 */
class LaravelFoodOrderCompositionNotifier implements FoodOrderCompositionNotifierInterface
{
    public function __construct(
        private readonly FoodOrderCustomerCompositionMaxMessageBuilderInterface $compositionMessageBuilder,
        private readonly FoodOrderCustomerMaxDispatchHelper $dispatchHelper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notifyCompositionChanged(FoodOrderRecord $order): void
    {
        $text = $this->compositionMessageBuilder->buildCustomerCompositionChanged($order);
        $buttonRows = $this->dispatchHelper->buildOpenAppButtonRows($order->id);

        $this->dispatchHelper->trySendMessage($text, $order, $buttonRows);
    }
}
