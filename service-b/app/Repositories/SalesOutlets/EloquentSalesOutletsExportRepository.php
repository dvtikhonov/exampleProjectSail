<?php

namespace App\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Enums\SalesOutletExportStatus;
use App\Models\SalesOutletExportJob;
use Illuminate\Support\Str;

class EloquentSalesOutletsExportRepository implements SalesOutletsExportRepositoryInterface
{
    public function create(SalesOutletExportFilterDto $filters, ?int $userId): SalesOutletExportJob
    {
        return SalesOutletExportJob::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'status' => SalesOutletExportStatus::Pending,
            'filters' => $filters->toArray(),
        ]);
    }

    public function findByUuid(string $uuid): ?SalesOutletExportJob
    {
        return SalesOutletExportJob::query()
            ->where('uuid', $uuid)
            ->first();
    }

    public function updateStatus(
        SalesOutletExportJob $exportJob,
        SalesOutletExportStatus $status,
        ?string $filePath = null,
        ?string $errorMessage = null,
    ): SalesOutletExportJob {
        $exportJob->forceFill([
            'status' => $status,
            'file_path' => $filePath ?? $exportJob->file_path,
            'error_message' => $errorMessage,
        ])->save();

        return $exportJob->refresh();
    }
}
