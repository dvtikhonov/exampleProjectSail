<?php

namespace App\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Enums\SalesOutletExportStatus;
use App\Models\SalesOutletMailJob;

interface SalesOutletsMailRepositoryInterface
{
    public function create(SalesOutletExportFilterDto $filters, ?int $userId): SalesOutletMailJob;

    public function findByUuid(string $uuid): ?SalesOutletMailJob;

    public function updateStatus(
        SalesOutletMailJob $mailJob,
        SalesOutletExportStatus $status,
        ?string $errorMessage = null,
    ): SalesOutletMailJob;
}
