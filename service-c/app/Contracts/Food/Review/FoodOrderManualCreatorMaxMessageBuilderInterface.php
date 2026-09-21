<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Сборка текста MAX-уведомления менеджеру, оформившему ручной заказ.
 */
interface FoodOrderManualCreatorMaxMessageBuilderInterface
{
    /**
     * Текст доп. уведомления менеджеру, оформившему ручной заказ, после подтверждения.
     */
    public function buildManualOrderCreatorConfirmed(
        FoodOrderRecord $order,
        int $maxTextLength = 4000,
    ): string;
}
