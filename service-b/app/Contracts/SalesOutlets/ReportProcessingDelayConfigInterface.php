<?php

namespace App\Contracts\SalesOutlets;

use App\Enums\SalesOutletsReportType;

interface ReportProcessingDelayConfigInterface
{
    public function applyFakeDelay(): bool;

    public function fakeDelaySeconds(SalesOutletsReportType $reportType): int;
}
