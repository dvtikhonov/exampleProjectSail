<?php

namespace App\Services\SalesOutlets\Reports;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Services\SalesOutlets\SalesOutletColumnSelector;
use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

trait ResolvesSalesOutletsReportData
{
    protected SalesOutletsDataRepositoryInterface $dataRepository;

    protected SalesOutletColumnSelector $columnSelector;

    /**
     * @return array<int, array{key: string, label: string}>
     */
    protected function resolveColumns(SalesOutletReportContextDto $context): array
    {
        return $this->columnSelector->select($this->toReportFilter($context));
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    protected function resolveRows(SalesOutletReportContextDto $context, array $columns): iterable
    {
        foreach ($this->dataRepository->reportRows($this->toReportFilter($context)) as $row) {
            yield $row;
        }
    }

    protected function toReportFilter(SalesOutletReportContextDto $context): SalesOutletReportFilterDto
    {
        return new SalesOutletReportFilterDto(
            search: $context->filters->search,
            status: $context->filters->status,
            columnFilters: $context->filters->columnFilters,
            sort: $context->filters->sort,
            direction: $context->filters->direction,
            columns: $context->columns,
        );
    }
}
