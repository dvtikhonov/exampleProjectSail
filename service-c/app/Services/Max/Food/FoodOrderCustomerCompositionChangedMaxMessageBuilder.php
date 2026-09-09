<?php

declare(strict_types=1);

namespace App\Services\Max\Food;

use App\DTO\Food\Order\FoodOrderRecord;
use App\Support\Max\Food\Formatting\FoodOrderMaxBoundedItemsMessageAssembler;
use App\Support\Max\Food\Formatting\FoodOrderMaxDateFormatter;
use App\Support\Max\Food\Formatting\FoodOrderMaxItemsExtractor;
use App\Support\Max\Food\Formatting\FoodOrderMaxMoneyFormatter;
use App\Support\Max\Food\Formatting\FoodOrderMaxTextAssembler;

/**
 * Текст MAX-уведомления клиенту об окончательном варианте заказа после правки состава.
 */
final class FoodOrderCustomerCompositionChangedMaxMessageBuilder
{
    public function __construct(
        private readonly FoodOrderMaxDateFormatter $dateFormatter,
        private readonly FoodOrderMaxMoneyFormatter $moneyFormatter,
        private readonly FoodOrderMaxItemsExtractor $itemsExtractor,
        private readonly FoodOrderMaxBoundedItemsMessageAssembler $boundedItemsAssembler,
    ) {}

    /**
     * Собирает текст уведомления с учётом лимита символов.
     */
    public function buildCustomerCompositionChanged(
        FoodOrderRecord $order,
        int $maxTextLength = FoodOrderMaxTextAssembler::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        $headerLines = [
            'Заказ изменен по вашему согласованию',
            sprintf('Заказ №%d', $order->id),
            sprintf('Ресторан: %s', (string) ($order->restaurantName ?? '')),
        ];

        $address = trim((string) ($order->deliveryAddress ?? ''));

        if ($address !== '') {
            $headerLines[] = sprintf('Адрес: %s', $address);
        }

        $deliveryDateLabel = $this->dateFormatter->formatDeliveryDateLabel($order->deliveryDate);

        if ($deliveryDateLabel !== null) {
            $headerLines[] = sprintf('Дата доставки: %s', $deliveryDateLabel);
        }

        $header = implode("\n", $headerLines);

        $footerLines = [
            sprintf('Сумма блюд: %s ₽', $this->moneyFormatter->formatMoneyAmount($order->itemsTotal)),
        ];

        if ($order->deliveryCost !== null) {
            $footerLines[] = sprintf(
                'Доставка: %s ₽',
                $this->moneyFormatter->formatMoneyAmount($order->deliveryCost),
            );
        }

        $footerLines[] = sprintf('Итого: %s ₽', $this->moneyFormatter->formatMoneyAmount($order->total));
        $footer = implode("\n", $footerLines);

        return $this->boundedItemsAssembler->assembleWithBulletItems(
            $header,
            $this->itemsExtractor->extractItemsFromSnapshot($order->itemsSnapshot),
            $footer,
            $maxTextLength,
        );
    }
}
