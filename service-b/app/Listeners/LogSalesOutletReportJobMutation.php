<?php

namespace App\Listeners;

use App\Events\SalesOutletReportJobMutated;
use Psr\Log\LoggerInterface;

class LogSalesOutletReportJobMutation
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(SalesOutletReportJobMutated $event): void
    {
        $this->logger->info('Sales outlet report job mutated.', [
            'uuid' => $event->uuid,
        ]);
    }
}
