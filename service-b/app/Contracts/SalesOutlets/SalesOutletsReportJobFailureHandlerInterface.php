<?php

namespace App\Contracts\SalesOutlets;

interface SalesOutletsReportJobFailureHandlerInterface
{
    public function handle(string $uuid, ?string $errorMessage): void;
}
