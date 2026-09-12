<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Http\Controllers;

use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Http\Controllers\Controller;
use App\Modules\FoodReport\Contracts\FoodReportMaxDeliveryInterface;
use App\Modules\FoodReport\Contracts\FoodReportSpreadsheetExporterInterface;
use App\Modules\FoodReport\DTO\ReportFilterDto;
use App\Modules\FoodReport\Enums\ReportType;
use App\Modules\FoodReport\Http\Requests\ExportFoodReportRequest;
use Illuminate\Http\JsonResponse;
use Shared\MaxMessenger\Exceptions\MaxMessengerException;

/**
 * Экспорт отчётов Food в .xlsx и отправка файла пользователю MAX (max_manager).
 */
class AdminFoodReportExportController extends Controller
{
    public function __construct(
        private readonly FoodReportSpreadsheetExporterInterface $exporter,
        private readonly FoodReportMaxDeliveryInterface $delivery,
        private readonly AuthenticatedMaxUserResolverInterface $maxUserResolver,
    ) {}

    /**
     * Генерация .xlsx и доставка в диалог текущего менеджера MAX.
     *
     * Валидация: date_from, date_to, restaurant_id, report_type; опц. date_axis, limit.
     */
    public function export(ExportFoodReportRequest $request): JsonResponse
    {
        $filter = $request->toFilterDto();
        $reportType = $request->reportType();
        $binary = $this->exporter->export(
            $reportType,
            $filter,
            $request->limitPerDay(),
        );

        $filename = $this->filename($filter);
        $maxUserId = $this->maxUserResolver->identity()->maxUserId;

        try {
            $this->delivery->deliver(
                $maxUserId,
                $binary,
                $filename,
                $this->messageText($reportType, $filter, $filename),
            );
        } catch (MaxMessengerException $exception) {
            return response()->json([
                'message' => $exception->userMessage(),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'filename' => $filename,
            'message' => 'Отчёт отправлен в чат MAX.',
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
