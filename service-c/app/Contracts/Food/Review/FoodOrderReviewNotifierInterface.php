<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\Enums\Food\Review\FoodOrderReviewNotifyKind;
use App\Enums\Food\Review\OrderRejectionScope;

/**
 * Порт постановки MAX-уведомлений после commit review / правки состава.
 *
 * Изолирует Food-сервисы от Laravel Job / Bus.
 */
interface FoodOrderReviewNotifierInterface
{
    /**
     * Ставит в очередь уведомление клиенту после review или правки состава.
     *
     * @param  int  $orderId  ID заказа в max_food_orders
     * @param  FoodOrderReviewNotifyKind  $kind  Тип уведомления
     * @param  OrderRejectionScope|null  $rejectionScope  Scope при Rejected
     * @param  string|null  $idempotencySuffix  Суффикс маркера (например updatedAt для CompositionChanged)
     */
    public function notify(
        int $orderId,
        FoodOrderReviewNotifyKind $kind,
        ?OrderRejectionScope $rejectionScope = null,
        ?string $idempotencySuffix = null,
    ): void;
}
