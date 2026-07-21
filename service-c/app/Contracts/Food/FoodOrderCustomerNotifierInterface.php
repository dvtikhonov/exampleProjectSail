<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Enums\Food\OrderRejectionScope;
use App\Models\FoodOrder;

/**
 * Уведомление клиента MAX о статусе заказа.
 */
interface FoodOrderCustomerNotifierInterface
{
    /**
     * Сообщает клиенту, что заказ принят на рассмотрение.
     */
    public function notifySubmitted(FoodOrder $order): void;

    /**
     * Сообщает клиенту, что заявка принята к исполнению.
     *
     * Для ручного заказа дополнительно отправляет детальный состав менеджеру
     * из created_by_max_user_id.
     */
    public function notifyConfirmed(FoodOrder $order): void;

    /**
     * Сообщает клиенту об отклонении заявки с указанием этапа и причины.
     */
    public function notifyRejected(FoodOrder $order, OrderRejectionScope $scope): void;

    /**
     * Сообщает клиенту окончательный вариант заказа после правки состава.
     */
    public function notifyCompositionChanged(FoodOrder $order): void;
}
