<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Enums;

/**
 * Ось даты для фильтра отчётов Food.
 */
enum ReportDateAxis: string
{
    /** Дата доставки («Блюда на»), fallback на DATE(created_at) в query. */
    case DeliveryDate = 'delivery_date';

    /** Дата создания заказа. */
    case CreatedAt = 'created_at';
}
