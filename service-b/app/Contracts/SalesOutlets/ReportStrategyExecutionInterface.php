<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;

interface ReportStrategyExecutionInterface
{
    public function execute(SalesOutletAsyncJob $job): ReportDeliveryResult;
}
