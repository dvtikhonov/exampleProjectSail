<?php

declare(strict_types=1);

namespace App\Services\Max\Food;

use App\Contracts\Food\Review\FoodOrderUiStandNewRequestMaxMessageBuilderInterface;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserDisplayDto;
use App\Support\Max\Food\Formatting\FoodOrderMaxBoundedItemsMessageAssembler;
use App\Support\Max\Food\Formatting\FoodOrderMaxClientFormatter;
use App\Support\Max\Food\Formatting\FoodOrderMaxDateFormatter;
use App\Support\Max\Food\Formatting\FoodOrderMaxItemsExtractor;
use App\Support\Max\Food\Formatting\FoodOrderMaxTextAssembler;

/**
 * Текст MAX-уведомления о новой заявке в UI Stand.
 */
final class FoodOrderUiStandNewRequestMaxMessageBuilder implements FoodOrderUiStandNewRequestMaxMessageBuilderInterface
{
    public function __construct(
        private readonly FoodOrderMaxClientFormatter $clientFormatter,
        private readonly FoodOrderMaxDateFormatter $dateFormatter,
        private readonly FoodOrderMaxItemsExtractor $itemsExtractor,
        private readonly FoodOrderMaxBoundedItemsMessageAssembler $boundedItemsAssembler,
    ) {}

    /**
     * Собирает текст уведомления о новой заявке с учётом лимита символов.
     */
    public function build(
        OrderDto $order,
        MaxUserDisplayDto $customer,
        int $maxTextLength = FoodOrderMaxTextAssembler::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        return $this->boundedItemsAssembler->assembleWithBulletItems(
            $this->buildHeader($order, $customer),
            $this->itemsExtractor->extractItems($order),
            $this->buildFooter($order),
            $maxTextLength,
        );
    }

    /**
     * Собирает заголовок MAX-сообщения о заказе.
     */
    private function buildHeader(OrderDto $order, MaxUserDisplayDto $customer): string
    {
        $lines = [
            sprintf('Новая заявка №%d', $order->id),
            sprintf('Ресторан: %s', $order->restaurantName),
            sprintf('Клиент: %s', $this->clientFormatter->formatClient($customer)),
        ];

        $address = trim((string) $order->deliveryAddress);

        if ($address !== '') {
            $lines[] = sprintf('Адрес: %s', $address);
        }

        $deliveryDateLabel = $this->dateFormatter->formatDeliveryDateLabel($order->deliveryDate);

        if ($deliveryDateLabel !== null) {
            $lines[] = sprintf('Дата доставки: %s', $deliveryDateLabel);
        }

        return implode("\n", $lines);
    }

    /**
     * Собирает подвал MAX-сообщения о заказе.
     */
    private function buildFooter(OrderDto $order): string
    {
        $lines = [
            'Статус: ожидает проверки адреса, состава и оплаты',
            sprintf('Сумма блюд: %s ₽', $order->itemsTotal),
        ];

        if ($order->deliveryApplicable) {
            $lines[] = sprintf('Доставка: %s ₽', $order->deliveryCost ?? '0.00');
        }

        $lines[] = sprintf('Итого: %s ₽', $order->total);

        return implode("\n", $lines);
    }
}
