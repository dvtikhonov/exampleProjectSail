<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;

interface ReportJobLifecycleInterface
{
    public function markProcessing(SalesOutletAsyncJob $job): SalesOutletAsyncJob;
}
