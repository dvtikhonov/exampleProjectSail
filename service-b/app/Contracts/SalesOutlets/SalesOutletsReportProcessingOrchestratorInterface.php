<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;

interface SalesOutletsReportProcessingOrchestratorInterface
{
    public function process(SalesOutletAsyncJob $job): void;
}
