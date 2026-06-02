<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;

/**
 * Entry point for queue/worker report processing.
 *
 * Delegates to {@see SalesOutletsReportProcessingOrchestratorInterface}.
 */
interface SalesOutletsReportJobProcessorInterface
{
    public function process(SalesOutletAsyncJob $job): void;
}
