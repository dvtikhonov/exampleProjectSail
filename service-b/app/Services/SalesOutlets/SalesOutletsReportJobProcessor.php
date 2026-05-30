<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Contracts\SalesOutlets\ReportProcessingDelayInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportContextFactoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportJobProcessorInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStrategyResolverInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\Enums\AsyncJobStatus;

class SalesOutletsReportJobProcessor implements SalesOutletsReportJobProcessorInterface
{
    public function __construct(
        private readonly SalesOutletsAsyncJobRepositoryInterface $reportRepository,
        private readonly SalesOutletsReportStrategyResolverInterface $strategyResolver,
        private readonly ReportProcessingDelayInterface $processingDelay,
        private readonly SalesOutletsReportContextFactoryInterface $contextFactory,
    ) {}

    public function process(SalesOutletAsyncJob $job): void
    {
        $job = $this->reportRepository->updateStatus($job, AsyncJobStatus::Processing);

        $this->processingDelay->apply($job->reportType);

        $strategy = $this->strategyResolver->resolve($job->reportType);
        $context = $this->contextFactory->fromJob($job);
        $content = $strategy->build($context);
        $delivery = $strategy->deliver($job, $content);

        $this->reportRepository->updateStatus(
            $job,
            AsyncJobStatus::Completed,
            filePath: $delivery->filePath,
        );
    }
}
