<?php

namespace App\Contracts\SalesOutlets;

use App\Enums\SalesOutletsReportType;

interface ReportProcessingDelayInterface
{
    public function apply(SalesOutletsReportType $reportType): void;
}
