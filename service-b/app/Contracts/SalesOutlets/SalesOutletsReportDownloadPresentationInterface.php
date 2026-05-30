<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\Enums\SalesOutletsReportType;

interface SalesOutletsReportDownloadPresentationInterface
{
    public function downloadFileName(SalesOutletAsyncJob $job): string;

    public function downloadContentType(SalesOutletsReportType $type): string;
}
