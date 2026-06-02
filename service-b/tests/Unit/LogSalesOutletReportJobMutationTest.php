<?php

namespace Tests\Unit;

use App\Events\SalesOutletReportJobMutated;
use App\Listeners\LogSalesOutletReportJobMutation;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LogSalesOutletReportJobMutationTest extends TestCase
{
    public function test_handle_logs_mutation_with_uuid(): void
    {
        $uuid = '11111111-1111-1111-1111-111111111111';

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Sales outlet report job mutated.',
                ['uuid' => $uuid],
            );

        $listener = new LogSalesOutletReportJobMutation($logger);
        $listener->handle(new SalesOutletReportJobMutated($uuid));
    }
}
