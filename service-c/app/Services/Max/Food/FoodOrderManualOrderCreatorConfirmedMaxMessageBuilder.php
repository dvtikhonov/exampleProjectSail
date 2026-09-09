<?php

declare(strict_types=1);

namespace App\Services\Max\Food;

use App\DTO\Food\Order\FoodOrderRecord;
use App\Support\Max\Food\Formatting\FoodOrderMaxBoundedItemsMessageAssembler;
use App\Support\Max\Food\Formatting\FoodOrderMaxClientFormatter;
use App\Support\Max\Food\Formatting\FoodOrderMaxDateFormatter;
use App\Support\Max\Food\Formatting\FoodOrderMaxManualItemsFormatter;
use App\Support\Max\Food\Formatting\FoodOrderMaxTextAssembler;

/**
 * Текст доп. MAX-уведомления менеджеру, оформившему ручной заказ, после подтверждения.
 */
final class FoodOrderManualOrderCreatorConfirmedMaxMessageBuilder
{
    public function __construct(
        private readonly FoodOrderMaxClientFormatter $clientFormatter,
        private readonly FoodOrderMaxDateFormatter $dateFormatter,
        private readonly FoodOrderMaxManualItemsFormatter $manualItemsFormatter,
        private readonly FoodOrderMaxBoundedItemsMessageAssembler $boundedItemsAssembler,
    ) {}

    /**
     * Собирает текст уведомления с учётом лимита символов.
     */
    public function buildManualOrderCreatorConfirmed(
        FoodOrderRecord $order,
        int $maxTextLength = FoodOrderMaxTextAssembler::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        $header = sprintf(
            'Заказ на %s. от %s:',
            $this->dateFormatter->formatOrderDate($order->deliveryDate ?? $order->createdAt),
            $this->clientFormatter->formatCustomerDisplayNameFromRecord($order),
        );

        return $this->boundedItemsAssembler->assembleWithPrefixedItemLines(
            $header,
            $this->manualItemsFormatter->formatManualOrderItemLines($order->itemsSnapshot),
            $maxTextLength,
        );
    }
}
