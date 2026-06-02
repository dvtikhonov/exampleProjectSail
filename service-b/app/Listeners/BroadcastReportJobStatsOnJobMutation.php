<?php

namespace App\Listeners;

use App\Contracts\SalesOutlets\SalesOutletsReportStatsBroadcasterInterface;
use App\Events\SalesOutletReportJobMutated;

class BroadcastReportJobStatsOnJobMutation
{
    public function __construct(
        private readonly SalesOutletsReportStatsBroadcasterInterface $statsBroadcaster,
    ) {}

    public function handle(SalesOutletReportJobMutated $event): void
    {
        $this->statsBroadcaster->broadcastCurrentStats();
    }
}
