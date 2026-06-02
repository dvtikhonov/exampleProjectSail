<?php

namespace Tests\Unit;

use App\Contracts\SalesOutlets\SalesOutletsReportProcessingOrchestratorInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\SalesOutletsReportJobProcessor;
use PHPUnit\Framework\TestCase;

class SalesOutletsReportJobProcessorTest extends TestCase
{
    public function test_process_delegates_to_orchestrator(): void
    {
        $job = $this->makeJob(AsyncJobStatus::Pending, SalesOutletsReportType::CsvDownload);

        $orchestrator = $this->createMock(SalesOutletsReportProcessingOrchestratorInterface::class);
        $orchestrator
            ->expects($this->once())
            ->method('process')
            ->with($job);

        $processor = new SalesOutletsReportJobProcessor($orchestrator);

        $processor->process($job);
    }

    private function makeJob(
        AsyncJobStatus $status,
        SalesOutletsReportType $reportType,
    ): SalesOutletAsyncJob {
        return new SalesOutletAsyncJob(
            uuid: '11111111-1111-1111-1111-111111111111',
            userId: 10,
            status: $status,
            reportType: $reportType,
            filters: SalesOutletReportFilterDto::fromValidated(
                validated: ['columns' => ['id', 'shop']],
                allowedColumns: ['id', 'shop'],
            ),
        );
    }
}
