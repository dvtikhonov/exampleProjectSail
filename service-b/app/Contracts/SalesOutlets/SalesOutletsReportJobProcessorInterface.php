<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;

interface SalesOutletsReportJobProcessorInterface
{
    public function process(SalesOutletAsyncJob $job): void;
}
