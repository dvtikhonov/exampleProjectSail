<?php

namespace App\Services\SalesOutlets;

interface SalesOutletsMailWorkerServiceInterface
{
    public function sendByUuid(string $uuid): void;

    public function markAsFailed(string $uuid, ?string $errorMessage = null): void;
}
