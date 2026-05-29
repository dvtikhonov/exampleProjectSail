<?php

namespace App\Jobs;

use App\Services\SalesOutlets\SalesOutletsMailWorkerServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendSalesOutletsReportMailJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        private readonly string $uuid,
    ) {}

    public function handle(SalesOutletsMailWorkerServiceInterface $mailWorker): void
    {
        $mailWorker->sendByUuid($this->uuid);
    }

    public function failed(?Throwable $exception): void
    {
        app(SalesOutletsMailWorkerServiceInterface::class)->markAsFailed(
            uuid: $this->uuid,
            errorMessage: $exception?->getMessage(),
        );
    }
}
