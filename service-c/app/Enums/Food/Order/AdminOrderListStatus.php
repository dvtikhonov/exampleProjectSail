<?php

declare(strict_types=1);

namespace App\Enums\Food\Order;

/**
 * Фильтр статуса в списке заказов админ-API проверки.
 */
enum AdminOrderListStatus: string
{
    case Pending = 'pending';
    case All = 'all';
}
