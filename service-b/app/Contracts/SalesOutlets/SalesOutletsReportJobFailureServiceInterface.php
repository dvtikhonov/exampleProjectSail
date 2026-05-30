<?php

namespace App\Contracts\SalesOutlets;

interface SalesOutletsReportJobFailureServiceInterface
{
    public function markAsFailed(string $uuid, ?string $errorMessage = null): void;
}
