<?php

namespace App\Jobs;

use App\Contracts\SalesOutlets\SalesOutletsReportJobFailureHandlerInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessorWorkerInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class BuildSalesOutletsReportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        private readonly string $uuid,
    ) {}

    public function handle(
        SalesOutletsReportProcessorWorkerInterface $reportWorker,
    ): void {
        $reportWorker->processByUuid($this->uuid);
    }

    public function failed(
        ?Throwable $exception,
        SalesOutletsReportJobFailureHandlerInterface $failureHandler,
    ): void {
        $failureHandler->handle($this->uuid, $exception?->getMessage());
    }
}
