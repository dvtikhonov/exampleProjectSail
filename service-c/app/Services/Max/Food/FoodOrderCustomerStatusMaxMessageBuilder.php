<?php

declare(strict_types=1);

namespace App\Services\Max\Food;

use App\Contracts\Food\Review\FoodOrderCustomerStatusMaxMessageBuilderInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Review\OrderRejectionScope;

/**
 * Тексты MAX-уведомлений клиенту о статусе заявки (принято / подтверждено / отклонено).
 */
final class FoodOrderCustomerStatusMaxMessageBuilder implements FoodOrderCustomerStatusMaxMessageBuilderInterface
{
    /**
     * Текст уведомления клиенту о принятии заказа на рассмотрение.
     */
    public function buildCustomerSubmitted(FoodOrderRecord $order): string
    {
        return sprintf(
            'Заказ №%d принят на рассмотрение. В чате заказа можете сделать уточнения к заказу',
            $order->id,
        );
    }

    /**
     * Текст уведомления клиенту о подтверждении заявки.
     */
    public function buildCustomerConfirmed(FoodOrderRecord $order): string
    {
        return sprintf('Заявка №%d принята к исполнению', $order->id);
    }

    /**
     * Текст уведомления клиенту об отклонении заявки.
     */
    public function buildCustomerRejected(FoodOrderRecord $order, OrderRejectionScope $scope): string
    {
        $comment = match ($scope) {
            OrderRejectionScope::Address => trim((string) ($order->addressRejectionComment ?? '')),
            OrderRejectionScope::Composition => trim((string) ($order->compositionRejectionComment ?? '')),
            OrderRejectionScope::Payment => trim((string) ($order->paymentRejectionComment ?? '')),
        };

        $lines = [
            sprintf('Заявка №%d отклонена', $order->id),
            sprintf('Проверка: %s', $scope->label()),
        ];

        if ($comment !== '') {
            $lines[] = sprintf('Причина: %s', $comment);
        }

        return implode("\n", $lines);
    }
}
