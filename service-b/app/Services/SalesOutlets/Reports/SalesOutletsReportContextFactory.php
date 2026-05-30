<?php

namespace App\Services\SalesOutlets\Reports;

use App\Contracts\SalesOutlets\SalesOutletsReportContextFactoryInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use Shared\SalesOutletsDomain\DTO\SalesOutletFilterDto;
use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

final class SalesOutletsReportContextFactory implements SalesOutletsReportContextFactoryInterface
{
    public function fromReportFilter(SalesOutletReportFilterDto $reportFilter): SalesOutletReportContextDto
    {
        return SalesOutletReportContextDto::fromFilterValues(
            filters: new SalesOutletFilterDto(
                search: $reportFilter->search,
                status: $reportFilter->status,
                columnFilters: $reportFilter->columnFilters,
                sort: $reportFilter->sort,
                direction: $reportFilter->direction,
            ),
            columnKeys: $reportFilter->columns,
        );
    }

    public function fromJob(SalesOutletAsyncJob $job): SalesOutletReportContextDto
    {
        return $this->fromReportFilter($job->filters);
    }
}
