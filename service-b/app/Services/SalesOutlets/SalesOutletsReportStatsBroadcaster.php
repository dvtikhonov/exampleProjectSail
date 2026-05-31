<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\SalesOutletsReportStatsBroadcasterInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStatsServiceInterface;
use App\Events\ReportJobStatsChanged;

class SalesOutletsReportStatsBroadcaster implements SalesOutletsReportStatsBroadcasterInterface
{
    public function __construct(
        private readonly SalesOutletsReportStatsServiceInterface $statsService,
    ) {}

    public function broadcastCurrentStats(): void
    {
        ReportJobStatsChanged::dispatch(
            $this->statsService->aggregate(),
        );
    }
}
