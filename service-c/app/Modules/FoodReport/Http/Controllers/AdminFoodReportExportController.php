<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Http\Controllers;

use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Http\Controllers\Controller;
use App\Modules\FoodReport\DTO\ReportFilterDto;
use App\Modules\FoodReport\Enums\ReportType;
use App\Modules\FoodReport\Http\Requests\ExportFoodReportRequest;
use App\Modules\FoodReport\Jobs\ExportFoodReportToMaxJob;
use Illuminate\Http\JsonResponse;

/**
 * Постановка экспорта Food Report в очередь (файл уйдёт в чат MAX асинхронно).
 */
class AdminFoodReportExportController extends Controller
{
    public function __construct(
        private readonly JobDispatcherInterface $jobDispatcher,
        private readonly AuthenticatedMaxUserResolverInterface $maxUserResolver,
    ) {}

    /**
     * Валидация → dispatch ExportFoodReportToMaxJob → 200 { queued: true }.
     *
     * Валидация: date_from, date_to, restaurant_id, report_type; опц. date_axis, limit.
     */
    public function export(ExportFoodReportRequest $request): JsonResponse
    {
        $filter = $request->toFilterDto();
        $reportType = $request->reportType();
        $filename = $this->filename($filter);
        $maxUserId = $this->maxUserResolver->identity()->maxUserId;

        $this->jobDispatcher->dispatch(new ExportFoodReportToMaxJob(
            maxUserId: $maxUserId,
            reportType: $reportType,
            filter: $filter,
            limitPerDay: $request->limitPerDay(),
            filename: $filename,
            messageText: $this->messageText($reportType, $filter, $filename),
        ));

        return response()->json([
            'ok' => true,
            'queued' => true,
            'filename' => $filename,
            'message' => 'Отчёт будет отправлен в чат MAX.',
        ]);
    }

    private function filename(ReportFilterDto $filter): string
    {
        return sprintf(
            'report_%d_%s_%s.xlsx',
            $filter->restaurantId,
            $filter->dateFrom,
            $filter->dateTo,
        );
    }

    private function messageText(ReportType $type, ReportFilterDto $filter, string $filename): string
    {
        return implode("\n", [
            'Отчёт: '.$type->label(),
            sprintf('Период: %s — %s', $filter->dateFrom, $filter->dateTo),
            'Ресторан ID: '.$filter->restaurantId,
            'Файл: '.$filename,
        ]);
    }
}
