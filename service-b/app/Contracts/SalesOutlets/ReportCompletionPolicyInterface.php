<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;

interface ReportCompletionPolicyInterface
{
    public function complete(SalesOutletAsyncJob $job, ReportDeliveryResult $delivery): void;
}
