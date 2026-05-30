<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportJobFailureServiceInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportJobProcessorInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessorWorkerInterface;

class SalesOutletsReportWorkerService extends AbstractSalesOutletsAsyncJobService implements SalesOutletsReportJobFailureServiceInterface, SalesOutletsReportProcessorWorkerInterface
{
    public function __construct(
        private readonly SalesOutletsAsyncJobRepositoryInterface $reportRepository,
        private readonly SalesOutletsReportJobProcessorInterface $jobProcessor,
    ) {}

    public function processByUuid(string $uuid): void
    {
        $job = $this->reportRepository->findByUuid($uuid);

        if ($job === null) {
            return;
        }

        try {
            $this->jobProcessor->process($job);
        } catch (\Throwable $exception) {
            $this->markAsFailed($uuid, $exception->getMessage());

            throw $exception;
        }
    }

    public function markAsFailed(string $uuid, ?string $errorMessage = null): void
    {
        $this->markJobAsFailed($uuid, $errorMessage);
    }

    protected function jobRepository(): SalesOutletsAsyncJobRepositoryInterface
    {
        return $this->reportRepository;
    }

    protected function defaultFailureMessage(): string
    {
        return 'Report job failed.';
    }
}
