<?php

namespace App\Contracts\SalesOutlets;

interface SalesOutletsReportProcessorWorkerInterface
{
    public function processByUuid(string $uuid): void;
}
