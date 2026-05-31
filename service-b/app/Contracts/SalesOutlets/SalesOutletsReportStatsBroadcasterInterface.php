<?php

namespace App\Contracts\SalesOutlets;

interface SalesOutletsReportStatsBroadcasterInterface
{
    public function broadcastCurrentStats(): void;
}
