<?php

namespace App\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Models\SalesOutlet;
use Illuminate\Support\Collection;
use Shared\SalesOutletsDomain\DTO\SalesOutletFilterDto;
use Shared\SalesOutletsDomain\DTO\SalesOutletRowDto;
use Shared\SalesOutletsDomain\Query\SalesOutletQueryFilter;

class EloquentSalesOutletsDataRepository implements SalesOutletsDataRepositoryInterface
{
    public function __construct(
        private readonly SalesOutletsExportMetadataRepositoryInterface $metadataRepository,
        private readonly SalesOutletQueryFilter $queryFilter = new SalesOutletQueryFilter(),
    ) {}

    public function exportRows(SalesOutletExportFilterDto $filters): Collection
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
