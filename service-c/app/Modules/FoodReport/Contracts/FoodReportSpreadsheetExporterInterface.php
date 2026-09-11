<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Contracts;

use App\Modules\FoodReport\DTO\ReportFilterDto;
use App\Modules\FoodReport\Enums\ReportType;

/**
 * Экспорт отчёта Food в бинарный .xlsx (PhpSpreadsheet).
 */
interface FoodReportSpreadsheetExporterInterface
{
    /**
     * Собирает XLSX по типу отчёта и возвращает содержимое файла.
     */
    public function export(ReportType $type, ReportFilterDto $filter, int $limitPerDay = 20): string;
}
