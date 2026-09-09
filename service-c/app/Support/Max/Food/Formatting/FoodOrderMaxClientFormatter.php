<?php

declare(strict_types=1);

namespace App\Support\Max\Food\Formatting;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserDisplayDto;

/**
 * Форматирование данных клиента для MAX-уведомлений о заказе.
 */
final class FoodOrderMaxClientFormatter
{
    /**
     * Форматирует данные клиента для сообщения.
     */
    public function formatClient(MaxUserDisplayDto $customer): string
    {
        $name = trim(implode(' ', array_filter([
            $customer->firstName,
            $customer->lastName,
        ])));

        $details = [];

        if ($customer->username !== null && trim($customer->username) !== '') {
            $details[] = '@'.trim($customer->username);
        }

        $details[] = 'id '.$customer->maxUserId;

        $detailsText = implode(', ', $details);

        if ($name !== '') {
            return $name.' ('.$detailsText.')';
        }

        return $detailsText;
    }

    /**
     * Отображаемое имя клиента из проекции заказа.
     */
    public function formatCustomerDisplayNameFromRecord(FoodOrderRecord $order): string
    {
        $name = trim(implode(' ', array_filter([
            $order->customerFirstName,
            $order->customerLastName,
        ])));

        if ($name !== '') {
            return $name;
        }

        if ($order->customerUsername !== null && trim($order->customerUsername) !== '') {
            return '@'.trim($order->customerUsername);
        }

        return 'id '.$order->maxUserId;
    }
}
