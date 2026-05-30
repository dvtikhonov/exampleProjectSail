<?php

namespace App\Contracts\SalesOutlets;

interface SalesOutletsJobQueueInterface
{
    public function dispatchReport(string $uuid): void;
}
