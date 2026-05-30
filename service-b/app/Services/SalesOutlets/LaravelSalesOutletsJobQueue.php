<?php

namespace App\Services\SalesOutlets;

use App\Contracts\Queue\JobDispatcherInterface;
use App\Contracts\SalesOutlets\SalesOutletsJobQueueInterface;
use App\Jobs\BuildSalesOutletsReportJob;

class LaravelSalesOutletsJobQueue implements SalesOutletsJobQueueInterface
{
    public function __construct(
        private readonly JobDispatcherInterface $jobDispatcher,
    ) {}

    public function dispatchReport(string $uuid): void
    {
        $this->jobDispatcher->dispatch(new BuildSalesOutletsReportJob(uuid: $uuid));
    }
}
