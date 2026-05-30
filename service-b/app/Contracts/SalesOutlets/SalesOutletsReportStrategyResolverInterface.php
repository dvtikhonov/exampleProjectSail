<?php

namespace App\Contracts\SalesOutlets;

use App\Enums\SalesOutletsReportType;

interface SalesOutletsReportStrategyResolverInterface
{
    public function resolve(SalesOutletsReportType $type): SalesOutletsReportProcessingStrategyInterface;
}
