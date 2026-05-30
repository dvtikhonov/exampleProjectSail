<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\SalesOutletsReportType;

interface SalesOutletsReportApiServiceInterface
{
    public function create(
        SalesOutletReportFilterDto $filters,
        ?int $userId,
        SalesOutletsReportType $reportType,
    ): SalesOutletAsyncJob;

    public function findByUuid(string $uuid): ?SalesOutletAsyncJob;
}
