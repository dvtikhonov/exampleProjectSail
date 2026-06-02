<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\SalesOutletsReportJobProcessorInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessingOrchestratorInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;

class SalesOutletsReportJobProcessor implements SalesOutletsReportJobProcessorInterface
{
    public function __construct(
        private readonly SalesOutletsReportProcessingOrchestratorInterface $orchestrator,
    ) {}

    public function process(SalesOutletAsyncJob $job): void
    {
        $this->orchestrator->process($job);
    }
}
