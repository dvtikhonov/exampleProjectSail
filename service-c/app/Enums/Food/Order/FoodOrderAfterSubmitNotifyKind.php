<?php

declare(strict_types=1);

namespace App\Enums\Food\Order;

/**
 * Тип клиентского уведомления после оформления заказа.
 */
enum FoodOrderAfterSubmitNotifyKind: string
{
    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
}
