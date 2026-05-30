<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\SalesOutletsReportJobFailureHandlerInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportJobFailureServiceInterface;

class SalesOutletsReportJobFailureHandler implements SalesOutletsReportJobFailureHandlerInterface
{
    public function __construct(
        private readonly SalesOutletsReportJobFailureServiceInterface $failureService,
    ) {}

    public function handle(string $uuid, ?string $errorMessage): void
    {
        $this->failureService->markAsFailed($uuid, $errorMessage);
    }
}
