<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;

interface SalesOutletsAsyncJobRepositoryInterface
{
    public function create(
        SalesOutletReportFilterDto $filters,
        ?int $userId,
        SalesOutletsReportType $reportType,
    ): SalesOutletAsyncJob;

    public function findByUuid(string $uuid): ?SalesOutletAsyncJob;

    public function updateStatus(
        SalesOutletAsyncJob $job,
        AsyncJobStatus $status,
        ?string $filePath = null,
        ?string $errorMessage = null,
    ): SalesOutletAsyncJob;
}
