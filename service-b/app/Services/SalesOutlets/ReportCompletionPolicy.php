<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ReportCompletionPolicyInterface;
use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\Enums\AsyncJobStatus;

class ReportCompletionPolicy implements ReportCompletionPolicyInterface
{
    public function __construct(
        private readonly SalesOutletsAsyncJobRepositoryInterface $reportRepository,
    ) {}

    public function complete(SalesOutletAsyncJob $job, ReportDeliveryResult $delivery): void
    {
        $this->reportRepository->updateStatus(
            $job,
            AsyncJobStatus::Completed,
            filePath: $delivery->filePath,
        );
    }
}
