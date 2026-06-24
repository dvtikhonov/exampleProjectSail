<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Enums\Food\OrderRejectionScope;
use App\Models\FoodOrder;

/**
 * Уведомление клиента MAX о результате проверки заказа.
 */
interface FoodOrderCustomerNotifierInterface
{
    /**
     * Сообщает клиенту, что заявка принята к исполнению.
     */
    public function notifyConfirmed(FoodOrder $order): void;

    /**
     * Сообщает клиенту об отклонении заявки с указанием этапа и причины.
     */
    public function notifyRejected(FoodOrder $order, OrderRejectionScope $scope): void;
}
