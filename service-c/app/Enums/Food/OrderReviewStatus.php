<?php

declare(strict_types=1);

namespace App\Enums\Food;

/**
 * Статус этапа проверки заказа (адрес или состав).
 */
enum OrderReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case NotApplicable = 'not_applicable';
}
