<?php

namespace App\Repositories\SalesOutlets;

use App\Contracts\SalesOutlets\SalesOutletsReportStatsServiceInterface;
use App\DTO\SalesOutlets\SalesOutletReportStatsDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Models\SalesOutletReportJob;
use Illuminate\Support\Carbon;

class EloquentSalesOutletsReportStatsService implements SalesOutletsReportStatsServiceInterface
{
    public function aggregate(): SalesOutletReportStatsDto
    {
        $byType = $this->emptyStatsByType();

        $rows = SalesOutletReportJob::query()
            ->selectRaw('report_type, status, COUNT(*) as count')
            ->groupBy('report_type', 'status')
            ->get();

        foreach ($rows as $row) {
            $reportType = $row->report_type instanceof SalesOutletsReportType
                ? $row->report_type->value
                : (string) $row->report_type;

            $status = $row->status instanceof AsyncJobStatus
                ? $row->status->value
                : (string) $row->status;

            $count = (int) $row->count;

            $byType[$reportType][$status] = $count;
            $byType[$reportType]['total'] += $count;
        }

        return new SalesOutletReportStatsDto(
            byType: $byType,
            generatedAt: Carbon::now()->toIso8601String(),
        );
    }

    /**
     * @return array<string, array{pending: int, processing: int, completed: int, failed: int, total: int}>
     */
    private function emptyStatsByType(): array
    {
        $byType = [];

        foreach (SalesOutletsReportType::cases() as $reportType) {
            $byType[$reportType->value] = [
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'total' => 0,
            ];
        }

        return $byType;
    }
}
