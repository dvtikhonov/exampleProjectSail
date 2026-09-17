<?php

declare(strict_types=1);

namespace App\Enums\Food\Review;

/**
 * Тип клиентского уведомления после review / правки состава.
 */
enum FoodOrderReviewNotifyKind: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case CompositionChanged = 'composition_changed';
}
