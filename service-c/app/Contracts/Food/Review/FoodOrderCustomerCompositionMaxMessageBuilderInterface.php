<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Сборка текста MAX-уведомления клиенту об изменении состава заказа.
 */
interface FoodOrderCustomerCompositionMaxMessageBuilderInterface
{
    /**
     * Текст уведомления клиенту об окончательном варианте заказа после правки состава.
     */
    public function buildCustomerCompositionChanged(
        FoodOrderRecord $order,
        int $maxTextLength = 4000,
    ): string;
}
