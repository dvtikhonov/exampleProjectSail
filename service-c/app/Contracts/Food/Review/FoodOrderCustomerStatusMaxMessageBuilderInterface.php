<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Review\OrderRejectionScope;

/**
 * Сборка текстов MAX-уведомлений клиенту о статусе заявки.
 */
interface FoodOrderCustomerStatusMaxMessageBuilderInterface
{
    /**
     * Текст уведомления клиенту о принятии заказа на рассмотрение.
     */
    public function buildCustomerSubmitted(FoodOrderRecord $order): string;

    /**
     * Текст уведомления клиенту о подтверждении заявки.
     */
    public function buildCustomerConfirmed(FoodOrderRecord $order): string;

    /**
     * Текст уведомления клиенту об отклонении заявки.
     */
    public function buildCustomerRejected(FoodOrderRecord $order, OrderRejectionScope $scope): string;
}
