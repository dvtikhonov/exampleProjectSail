<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\Enums\SalesOutletsReportType;
use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

interface SalesOutletsReportProcessingStrategyInterface
{
    public function reportType(): SalesOutletsReportType;

    public function build(SalesOutletReportContextDto $context): string;

    public function deliver(SalesOutletAsyncJob $job, string $content): ReportDeliveryResult;
}
