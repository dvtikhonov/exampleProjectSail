<?php

namespace App\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Enums\SalesOutletExportStatus;
use App\Models\SalesOutletMailJob;
use Illuminate\Support\Str;

class EloquentSalesOutletsMailRepository implements SalesOutletsMailRepositoryInterface
{
    public function create(SalesOutletExportFilterDto $filters, ?int $userId): SalesOutletMailJob
    {
        return SalesOutletMailJob::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'status' => SalesOutletExportStatus::Pending,
            'filters' => $filters->toArray(),
        ]);
    }

    public function findByUuid(string $uuid): ?SalesOutletMailJob
    {
        return SalesOutletMailJob::query()
            ->where('uuid', $uuid)
            ->first();
    }

    public function updateStatus(
        SalesOutletMailJob $mailJob,
        SalesOutletExportStatus $status,
        ?string $errorMessage = null,
    ): SalesOutletMailJob {
        $mailJob->forceFill([
            'status' => $status,
            'error_message' => $errorMessage,
        ])->save();

        return $mailJob->refresh();
    }
}
