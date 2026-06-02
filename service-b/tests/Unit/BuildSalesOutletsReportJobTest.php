<?php

namespace Tests\Unit;

use App\Contracts\SalesOutlets\SalesOutletsReportJobFailureHandlerInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessorWorkerInterface;
use App\Jobs\BuildSalesOutletsReportJob;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BuildSalesOutletsReportJobTest extends TestCase
{
    public function test_handle_calls_failure_handler_and_rethrows_on_worker_exception(): void
    {
        $uuid = '11111111-1111-1111-1111-111111111111';
        $exception = new RuntimeException('worker failed');

        $worker = $this->createMock(SalesOutletsReportProcessorWorkerInterface::class);
        $worker->expects($this->once())
            ->method('processByUuid')
            ->with($uuid)
            ->willThrowException($exception);

        $failureHandler = $this->createMock(SalesOutletsReportJobFailureHandlerInterface::class);
        $failureHandler->expects($this->once())
            ->method('handle')
            ->with($uuid, 'worker failed');

        $job = new BuildSalesOutletsReportJob($uuid);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('worker failed');

        $job->handle($worker, $failureHandler);
    }
}
