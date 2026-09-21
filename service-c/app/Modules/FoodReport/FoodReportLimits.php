<?php

declare(strict_types=1);

namespace App\Modules\FoodReport;

/**
 * Модульные лимиты отчётов Food (без привязки к HTTP-слою).
 */
final class FoodReportLimits
{
    /**
     * Максимальная длина периода отчёта в днях (включительно: from..to).
     */
    public const int MAX_SPAN_DAYS = 93;
}
