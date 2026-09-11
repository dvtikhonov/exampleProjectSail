<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FoodReport\Contracts\FoodReportSpreadsheetExporterInterface;
use App\Modules\FoodReport\Http\Requests\ExportFoodReportRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Экспорт отчётов Food в .xlsx для max_manager (один лист по report_type).
 */
class AdminFoodReportExportController extends Controller
{
    public function __construct(
        private readonly FoodReportSpreadsheetExporterInterface $exporter,
    ) {}

    /**
     * Скачивание .xlsx: report_type = revenue|top_dishes.
     */
    public function export(ExportFoodReportRequest $request): StreamedResponse
    {
        $filter = $request->toFilterDto();
        $binary = $this->exporter->export(
            $request->reportType(),
            $filter,
            $request->limitPerDay(),
        );

        $filename = sprintf(
            'report_%d_%s_%s.xlsx',
            $filter->restaurantId,
            $filter->dateFrom,
            $filter->dateTo,
        );

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }
}
