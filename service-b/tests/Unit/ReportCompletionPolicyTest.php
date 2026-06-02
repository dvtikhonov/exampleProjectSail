<?php

namespace Tests\Unit;

use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\ReportCompletionPolicy;
use PHPUnit\Framework\TestCase;

class ReportCompletionPolicyTest extends TestCase
{
    public function test_complete_marks_job_completed_with_file_path(): void
    {
        $job = $this->makeJob();
        $delivery = ReportDeliveryResult::withFile('reports/export.csv');

        $reportRepository = $this->createMock(SalesOutletsAsyncJobRepositoryInterface::class);
        $reportRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with(
                $job,
                AsyncJobStatus::Completed,
                'reports/export.csv',
            );

        $policy = new ReportCompletionPolicy(reportRepository: $reportRepository);

        $policy->complete($job, $delivery);
    }

    public function test_complete_marks_job_completed_without_file_path_when_delivery_has_none(): void
    {
        $job = $this->makeJob(SalesOutletsReportType::HtmlEmail);
        $delivery = ReportDeliveryResult::none();

        $reportRepository = $this->createMock(SalesOutletsAsyncJobRepositoryInterface::class);
        $reportRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with(
                $job,
                AsyncJobStatus::Completed,
                null,
            );

        $policy = new ReportCompletionPolicy(reportRepository: $reportRepository);

        $policy->complete($job, $delivery);
    }

    private function makeJob(
        SalesOutletsReportType $reportType = SalesOutletsReportType::CsvDownload,
    ): SalesOutletAsyncJob {
        return new SalesOutletAsyncJob(
            uuid: '44444444-4444-4444-4444-444444444444',
            userId: 3,
            status: AsyncJobStatus::Processing,
            reportType: $reportType,
            filters: SalesOutletReportFilterDto::fromValidated(
                validated: ['columns' => ['id']],
                allowedColumns: ['id'],
            ),
        );
    }
}
