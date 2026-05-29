<?php

namespace App\Services\SalesOutlets;

interface SalesOutletsExportWorkerServiceInterface
{
    public function buildByUuid(string $uuid): void;

    public function markAsFailed(string $uuid, ?string $errorMessage = null): void;
}
