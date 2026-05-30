<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsJobQueueInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportApiServiceInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\SalesOutletsReportType;

class SalesOutletsReportApiService implements SalesOutletsReportApiServiceInterface
{
    public function __construct(
        private readonly SalesOutletsAsyncJobRepositoryInterface $reportRepository,
        private readonly SalesOutletsJobQueueInterface $jobQueue,
    ) {}

    public function create(
        SalesOutletReportFilterDto $filters,
        ?int $userId,
        SalesOutletsReportType $reportType,
    ): SalesOutletAsyncJob {
        $job = $this->reportRepository->create($filters, $userId, $reportType);

        $this->jobQueue->dispatchReport($job->uuid);

        return $job;
    }

    public function findByUuid(string $uuid): ?SalesOutletAsyncJob
    {
        return $this->reportRepository->findByUuid($uuid);
    }
}
