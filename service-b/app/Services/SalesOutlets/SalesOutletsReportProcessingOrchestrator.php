<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ReportCompletionPolicyInterface;
use App\Contracts\SalesOutlets\ReportJobLifecycleInterface;
use App\Contracts\SalesOutlets\ReportStrategyExecutionInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessingOrchestratorInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;

class SalesOutletsReportProcessingOrchestrator implements SalesOutletsReportProcessingOrchestratorInterface
{
    public function __construct(
        private readonly ReportJobLifecycleInterface $lifecycle,
        private readonly ReportStrategyExecutionInterface $strategyExecution,
        private readonly ReportCompletionPolicyInterface $completionPolicy,
    ) {}

    public function process(SalesOutletAsyncJob $job): void
    {
        $job = $this->lifecycle->markProcessing($job);
        $delivery = $this->strategyExecution->execute($job);
        $this->completionPolicy->complete($job, $delivery);
    }
}
