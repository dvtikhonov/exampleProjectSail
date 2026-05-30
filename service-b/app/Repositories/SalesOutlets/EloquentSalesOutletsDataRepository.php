<?php

namespace App\Repositories\SalesOutlets;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Models\SalesOutlet;
use Illuminate\Support\Collection;
use Shared\SalesOutletsDomain\DTO\SalesOutletFilterDto;
use Shared\SalesOutletsDomain\DTO\SalesOutletRowDto;
use Shared\SalesOutletsDomain\Query\SalesOutletQueryFilter;

class EloquentSalesOutletsDataRepository implements SalesOutletsDataRepositoryInterface
{
    public function __construct(
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
        private readonly SalesOutletQueryFilter $queryFilter,
    ) {}

    public function reportRows(SalesOutletReportFilterDto $filters): Collection
    {
        $query = SalesOutlet::query();
        $allowedColumns = $this->metadataRepository->allowedColumnKeys();

        $this->queryFilter->apply(
            query: $query,
            filters: new SalesOutletFilterDto(
                search: $filters->search,
                status: $filters->status,
                columnFilters: $filters->columnFilters,
                sort: $filters->sort,
                direction: $filters->direction,
            ),
            allowedColumnKeys: $allowedColumns,
        );

        return $query->get()
            ->map(fn (SalesOutlet $salesOutlet): array => SalesOutletRowDto::fromModel($salesOutlet)->toArray(includeRowTone: false));
    }
}
