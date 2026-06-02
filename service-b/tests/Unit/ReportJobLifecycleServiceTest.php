<?php

namespace Tests\Unit;

use App\Contracts\SalesOutlets\ReportProcessingDelayInterface;
use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\ReportJobLifecycleService;
use PHPUnit\Framework\TestCase;

class ReportJobLifecycleServiceTest extends TestCase
{
    public function test_mark_processing_updates_status_and_applies_delay(): void
    {
        $pendingJob = $this->makeJob(AsyncJobStatus::Pending, SalesOutletsReportType::HtmlEmail);
        $processingJob = $this->makeJob(AsyncJobStatus::Processing, SalesOutletsReportType::HtmlEmail);

        $reportRepository = $this->createMock(SalesOutletsAsyncJobRepositoryInterface::class);
        $reportRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with($pendingJob, AsyncJobStatus::Processing)
            ->willReturn($processingJob);

        $processingDelay = $this->createMock(ReportProcessingDelayInterface::class);
        $processingDelay
            ->expects($this->once())
            ->method('apply')
            ->with(SalesOutletsReportType::HtmlEmail);

        $service = new ReportJobLifecycleService(
            reportRepository: $reportRepository,
            processingDelay: $processingDelay,
        );

        $result = $service->markProcessing($pendingJob);

        $this->assertSame($processingJob, $result);
    }

    private function makeJob(
        AsyncJobStatus $status,
        SalesOutletsReportType $reportType,
    ): SalesOutletAsyncJob {
        return new SalesOutletAsyncJob(
            uuid: '22222222-2222-2222-2222-222222222222',
            userId: 5,
            status: $status,
            reportType: $reportType,
            filters: SalesOutletReportFilterDto::fromValidated(
                validated: ['columns' => ['id']],
                allowedColumns: ['id'],
            ),
        );
    }
}
