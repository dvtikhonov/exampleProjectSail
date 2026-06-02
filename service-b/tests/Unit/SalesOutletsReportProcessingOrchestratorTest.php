<?php

namespace Tests\Unit;

use App\Contracts\SalesOutlets\ReportCompletionPolicyInterface;
use App\Contracts\SalesOutlets\ReportJobLifecycleInterface;
use App\Contracts\SalesOutlets\ReportStrategyExecutionInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\SalesOutletsReportProcessingOrchestrator;
use PHPUnit\Framework\TestCase;

class SalesOutletsReportProcessingOrchestratorTest extends TestCase
{
    public function test_process_runs_pipeline_in_order(): void
    {
        $pendingJob = $this->makeJob(AsyncJobStatus::Pending, SalesOutletsReportType::CsvDownload);
        $processingJob = $this->makeJob(AsyncJobStatus::Processing, SalesOutletsReportType::CsvDownload);
        $delivery = ReportDeliveryResult::withFile('reports/out.csv');

        $lifecycle = $this->createMock(ReportJobLifecycleInterface::class);
        $lifecycle
            ->expects($this->once())
            ->method('markProcessing')
            ->with($pendingJob)
            ->willReturn($processingJob);

        $strategyExecution = $this->createMock(ReportStrategyExecutionInterface::class);
        $strategyExecution
            ->expects($this->once())
            ->method('execute')
            ->with($processingJob)
            ->willReturn($delivery);

        $completionPolicy = $this->createMock(ReportCompletionPolicyInterface::class);
        $completionPolicy
            ->expects($this->once())
            ->method('complete')
            ->with($processingJob, $delivery);

        $orchestrator = new SalesOutletsReportProcessingOrchestrator(
            lifecycle: $lifecycle,
            strategyExecution: $strategyExecution,
            completionPolicy: $completionPolicy,
        );

        $orchestrator->process($pendingJob);
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
