<?php

namespace App\Contracts\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use Illuminate\Support\Collection;

interface SalesOutletsDataRepositoryInterface
{
    /**
     * @return Collection<int, array<string, int|string|null>>
     */
    public function reportRows(SalesOutletReportFilterDto $filters): Collection;
}
