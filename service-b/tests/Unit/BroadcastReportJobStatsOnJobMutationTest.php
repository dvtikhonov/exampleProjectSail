<?php

namespace Tests\Unit;

use App\Contracts\SalesOutlets\SalesOutletsReportStatsBroadcasterInterface;
use App\Events\SalesOutletReportJobMutated;
use App\Listeners\BroadcastReportJobStatsOnJobMutation;
use PHPUnit\Framework\TestCase;

class BroadcastReportJobStatsOnJobMutationTest extends TestCase
{
    public function test_handle_broadcasts_current_stats(): void
    {
        $broadcaster = $this->createMock(SalesOutletsReportStatsBroadcasterInterface::class);
        $broadcaster->expects($this->once())->method('broadcastCurrentStats');

        $listener = new BroadcastReportJobStatsOnJobMutation($broadcaster);

        $listener->handle(new SalesOutletReportJobMutated('11111111-1111-1111-1111-111111111111'));
    }
}
