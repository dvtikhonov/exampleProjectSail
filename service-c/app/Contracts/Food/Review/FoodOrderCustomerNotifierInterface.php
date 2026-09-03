<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Review\OrderRejectionScope;

/**
 * Уведомления клиента о статусе заказа еды через MAX.
 */
interface FoodOrderCustomerNotifierInterface
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
     * Уведомляет менеджера, оформившего ручной заказ, о подтверждении.
     */
    public function notifyManualOrderCreatorConfirmed(FoodOrderRecord $order): void;

    /**
     * Уведомляет клиента об отклонении заказа.
     */
    public function notifyRejected(FoodOrderRecord $order, OrderRejectionScope $scope): void;

    /**
     * Уведомляет клиента об изменении состава заказа.
     */
    public function notifyCompositionChanged(FoodOrderRecord $order): void;
}
