<?php

namespace App\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use Illuminate\Support\Collection;

interface SalesOutletsDataRepositoryInterface
{
    /**
     * @return Collection<int, array<string, int|string|null>>
     */
    public function exportRows(SalesOutletExportFilterDto $filters): Collection;
}
