<?php

namespace App\Jobs;

use App\Contracts\SalesOutlets\SalesOutletsReportJobFailureHandlerInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessorWorkerInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildSalesOutletsReportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        private readonly string $uuid,
    ) {}

    public function handle(
        SalesOutletsReportProcessorWorkerInterface $reportWorker,
        SalesOutletsReportJobFailureHandlerInterface $failureHandler,
    ): void {
        try {
            $reportWorker->processByUuid($this->uuid);
        } catch (\Throwable $exception) {
            $failureHandler->handle($this->uuid, $exception->getMessage());

            throw $exception;
        }
    }
}
