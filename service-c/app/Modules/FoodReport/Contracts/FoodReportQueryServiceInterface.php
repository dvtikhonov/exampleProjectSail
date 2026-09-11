<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Contracts;

use App\Modules\FoodReport\DTO\ReportFilterDto;
use App\Modules\FoodReport\DTO\RevenueReportDto;
use App\Modules\FoodReport\DTO\TopDishesReportDto;

/**
 * Query-сервис отчётов Food (выручка и топ блюд).
 */
interface FoodReportQueryServiceInterface
{
    /**
     * Выручка по дням за период (только confirmed).
     */
    public function revenue(ReportFilterDto $filter): RevenueReportDto;

    /**
     * Топ позиций по дням за период (только confirmed).
     */
    public function topDishes(ReportFilterDto $filter, int $limitPerDay = 20): TopDishesReportDto;
}
