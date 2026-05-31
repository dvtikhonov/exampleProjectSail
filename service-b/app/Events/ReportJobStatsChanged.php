<?php

namespace App\Events;

use App\DTO\SalesOutlets\SalesOutletReportStatsDto;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportJobStatsChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly SalesOutletReportStatsDto $stats,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('report-jobs.stats'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ReportJobStatsChanged';
    }

    /**
     * @return array{by_type: array<string, array{pending: int, processing: int, completed: int, failed: int, total: int}>, generated_at: string}
     */
    public function broadcastWith(): array
    {
        return $this->stats->toArray();
    }
}
