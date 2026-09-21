<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Review\OrderRejectionScope;

/**
 * Уведомления клиента о статусе заявки (отправка / подтверждение / отклонение).
 */
interface FoodOrderStatusNotifierInterface
{
    /**
     * Уведомляет клиента об отправке заказа на проверку.
     */
    public function notifySubmitted(FoodOrderRecord $order): void;

    /**
     * Уведомляет клиента о принятии заказа к исполнению.
     */
    public function notifyConfirmed(FoodOrderRecord $order): void;

    /**
     * Уведомляет клиента об отклонении заказа.
     */
    public function notifyRejected(FoodOrderRecord $order, OrderRejectionScope $scope): void;
}
