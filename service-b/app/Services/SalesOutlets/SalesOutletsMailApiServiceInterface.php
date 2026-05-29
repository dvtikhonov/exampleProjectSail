<?php

namespace App\Services\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Models\SalesOutletMailJob;

interface SalesOutletsMailApiServiceInterface
{
    public function create(SalesOutletExportFilterDto $filters, ?int $userId): SalesOutletMailJob;

    public function findByUuid(string $uuid): ?SalesOutletMailJob;
}
