<?php

namespace App\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Enums\SalesOutletExportStatus;
use App\Models\SalesOutletExportJob;

interface SalesOutletsExportRepositoryInterface
{
    public function create(SalesOutletExportFilterDto $filters, ?int $userId): SalesOutletExportJob;

    public function findByUuid(string $uuid): ?SalesOutletExportJob;

    public function updateStatus(
        SalesOutletExportJob $exportJob,
        SalesOutletExportStatus $status,
        ?string $filePath = null,
        ?string $errorMessage = null,
    ): SalesOutletExportJob;
}
