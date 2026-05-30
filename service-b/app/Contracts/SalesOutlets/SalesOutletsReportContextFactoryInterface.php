<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

interface SalesOutletsReportContextFactoryInterface
{
    public function fromReportFilter(SalesOutletReportFilterDto $reportFilter): SalesOutletReportContextDto;

    public function fromJob(SalesOutletAsyncJob $job): SalesOutletReportContextDto;
}
