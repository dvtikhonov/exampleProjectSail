<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ReportJobLifecycleInterface;
use App\Contracts\SalesOutlets\ReportProcessingDelayInterface;
use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\Enums\AsyncJobStatus;

class ReportJobLifecycleService implements ReportJobLifecycleInterface
{
    public function __construct(
        private readonly SalesOutletsAsyncJobRepositoryInterface $reportRepository,
        private readonly ReportProcessingDelayInterface $processingDelay,
    ) {}

    public function markProcessing(SalesOutletAsyncJob $job): SalesOutletAsyncJob
    {
        $job = $this->reportRepository->updateStatus($job, AsyncJobStatus::Processing);
        $this->processingDelay->apply($job->reportType);

        return $job;
    }
}
