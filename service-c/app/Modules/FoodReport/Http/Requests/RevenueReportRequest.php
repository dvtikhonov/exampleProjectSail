<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Http\Requests;

/**
 * Валидация GET /api/food/admin/reports/revenue.
 *
 * Поля: date_from, date_to, restaurant_id, опц. date_axis.
 */
class RevenueReportRequest extends FoodReportFilterRequest {}
