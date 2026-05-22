<?php

namespace App\Jobs;

use App\Services\SalesOutlets\SalesOutletsExportServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildSalesOutletsCsvExportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        private readonly string $uuid,
    ) {}

    public function handle(SalesOutletsExportServiceInterface $exportService): void
    {
        $exportJob = $exportService->findByUuid($this->uuid);

        if ($exportJob === null) {
            return;
        }

        $exportService->buildCsv($exportJob);
    }
}
