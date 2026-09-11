<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\DTO;

use App\Modules\FoodReport\Enums\ReportDateAxis;

/**
 * Фильтр периода и ресторана для отчётов Food.
 */
readonly class ReportFilterDto
{
    public function __construct(
        public string $dateFrom,
        public string $dateTo,
        public int $restaurantId,
        public ReportDateAxis $dateAxis = ReportDateAxis::DeliveryDate,
    ) {}
}
