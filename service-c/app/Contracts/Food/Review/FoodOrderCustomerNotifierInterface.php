<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

/**
 * Composition ISP: полный порт клиентских MAX-уведомлений о заказе.
 *
 * Предпочтительнее инжектить узкий порт:
 * {@see FoodOrderStatusNotifierInterface},
 * {@see FoodOrderCompositionNotifierInterface},
 * {@see FoodOrderManualCreatorNotifierInterface}.
 */
interface FoodOrderCustomerNotifierInterface extends
    FoodOrderStatusNotifierInterface,
    FoodOrderCompositionNotifierInterface,
    FoodOrderManualCreatorNotifierInterface
{
}
