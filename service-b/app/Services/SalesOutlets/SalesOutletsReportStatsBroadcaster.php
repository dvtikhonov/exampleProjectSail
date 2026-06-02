<?php

namespace App\Services\SalesOutlets;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsReportStatsRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStatsBroadcasterInterface;
use App\Events\ReportJobStatsChanged;

class SalesOutletsReportStatsBroadcaster implements SalesOutletsReportStatsBroadcasterInterface
{
    public function __construct(
        private readonly SalesOutletsReportStatsRepositoryInterface $statsRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function broadcastCurrentStats(): void
    {
        $this->eventDispatcher->dispatch(
            new ReportJobStatsChanged(
                $this->statsRepository->aggregate(),
            ),
        );
    }
}
