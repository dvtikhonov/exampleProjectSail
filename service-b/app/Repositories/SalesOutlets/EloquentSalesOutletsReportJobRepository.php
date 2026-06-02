<?php

namespace App\Repositories\SalesOutlets;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Events\SalesOutletReportJobMutated;
use App\Models\SalesOutletReportJob;
use Illuminate\Support\Str;

class EloquentSalesOutletsReportJobRepository implements SalesOutletsAsyncJobRepositoryInterface
{
    public function __construct(
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function create(
        SalesOutletReportFilterDto $filters,
        ?int $userId,
        SalesOutletsReportType $reportType,
    ): SalesOutletAsyncJob {
        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'report_type' => $reportType,
            'status' => AsyncJobStatus::Pending,
            'filters' => $filters->toArray(),
        ]);

        $this->dispatchMutated($reportJob->uuid);

        return $this->toAsyncJob($reportJob);
    }

    public function findByUuid(string $uuid): ?SalesOutletAsyncJob
    {
        $reportJob = SalesOutletReportJob::query()
            ->where('uuid', $uuid)
            ->first();

        return $reportJob === null ? null : $this->toAsyncJob($reportJob);
    }

    public function updateStatus(
        SalesOutletAsyncJob $job,
        AsyncJobStatus $status,
        ?string $filePath = null,
        ?string $errorMessage = null,
    ): SalesOutletAsyncJob {
        $reportJob = SalesOutletReportJob::query()
            ->where('uuid', $job->uuid)
            ->firstOrFail();

        $reportJob->forceFill([
            'status' => $status,
            'file_path' => $filePath ?? $reportJob->file_path,
            'error_message' => $errorMessage,
        ])->save();

        $asyncJob = $this->toAsyncJob($reportJob->refresh());

        $this->dispatchMutated($asyncJob->uuid);

        return $asyncJob;
    }

    private function dispatchMutated(string $uuid): void
    {
        $this->eventDispatcher->dispatch(new SalesOutletReportJobMutated($uuid));
    }

    private function toAsyncJob(SalesOutletReportJob $reportJob): SalesOutletAsyncJob
    {
        return SalesOutletAsyncJob::fromReportJob(
            $reportJob,
            SalesOutletReportFilterDto::fromStoredArray(
                $reportJob->filters ?? [],
                $this->metadataRepository->allowedColumnKeys(),
            ),
        );
    }
}
