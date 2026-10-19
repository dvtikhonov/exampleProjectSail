<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Food\Review\FoodOrderCompositionNotifierInterface;
use App\Contracts\Food\Review\FoodOrderCustomerCompositionMaxMessageBuilderInterface;
use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Уведомления клиента об изменении состава заказа (MAX / Telegram по каналу).
 */
class LaravelFoodOrderCompositionNotifier implements FoodOrderCompositionNotifierInterface
{
    public function __construct(
        private readonly FoodOrderCustomerCompositionMaxMessageBuilderInterface $compositionMessageBuilder,
        private readonly FoodOrderCustomerMessengerDispatchHelper $dispatchHelper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notifyCompositionChanged(FoodOrderRecord $order): void
    {
        $text = $this->compositionMessageBuilder->buildCustomerCompositionChanged($order);

        $this->dispatchHelper->trySendMessage($text, $order, withOpenAppButton: true);
    }
}
