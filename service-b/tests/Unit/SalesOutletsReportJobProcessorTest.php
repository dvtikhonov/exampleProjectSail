<?php

namespace Tests\Unit;

use App\Contracts\SalesOutlets\ReportProcessingDelayInterface;
use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsDownloadableReportStrategyInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportContextFactoryInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportProcessingStrategyInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportStrategyResolverInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\Reports\SalesOutletsReportContextFactory;
use App\Services\SalesOutlets\SalesOutletsReportJobProcessor;
use PHPUnit\Framework\TestCase;
use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

class SalesOutletsReportJobProcessorTest extends TestCase
{
    public function test_process_marks_csv_download_job_completed_with_file_path(): void
    {
        $pendingJob = $this->makeJob(AsyncJobStatus::Pending, SalesOutletsReportType::CsvDownload);
        $processingJob = $this->makeJob(AsyncJobStatus::Processing, SalesOutletsReportType::CsvDownload);
        $completedJob = $this->makeJob(
            AsyncJobStatus::Completed,
            SalesOutletsReportType::CsvDownload,
            filePath: 'reports/sales-outlets-test.csv',
        );

        $finalStatus = null;
        $finalFilePath = null;

        $reportRepository = $this->createMock(SalesOutletsAsyncJobRepositoryInterface::class);
        $reportRepository
            ->expects($this->exactly(2))
            ->method('updateStatus')
            ->willReturnCallback(function (
                SalesOutletAsyncJob $job,
                AsyncJobStatus $status,
                ?string $filePath = null,
            ) use ($processingJob, $completedJob, &$finalStatus, &$finalFilePath): SalesOutletAsyncJob {
                $finalStatus = $status;
                $finalFilePath = $filePath;

                return $status === AsyncJobStatus::Processing ? $processingJob : $completedJob;
            });

        $strategy = $this->createProcessingStrategyStub(
            SalesOutletsReportType::CsvDownload,
            supportsDownload: true,
            deliveryResult: ReportDeliveryResult::withFile('reports/sales-outlets-11111111-1111-1111-1111-111111111111.csv'),
        );

        $strategyResolver = $this->createMock(SalesOutletsReportStrategyResolverInterface::class);
        $strategyResolver
            ->expects($this->once())
            ->method('resolve')
            ->with(SalesOutletsReportType::CsvDownload)
            ->willReturn($strategy);

        $processingDelay = $this->createMock(ReportProcessingDelayInterface::class);
        $processingDelay
            ->expects($this->once())
            ->method('apply')
            ->with(SalesOutletsReportType::CsvDownload);

        $contextFactory = $this->createMock(SalesOutletsReportContextFactoryInterface::class);
        $realContextFactory = new SalesOutletsReportContextFactory;
        $contextFactory
            ->expects($this->once())
            ->method('fromJob')
            ->with($processingJob)
            ->willReturnCallback(fn (SalesOutletAsyncJob $job) => $realContextFactory->fromJob($job));

        $processor = new SalesOutletsReportJobProcessor(
            reportRepository: $reportRepository,
            strategyResolver: $strategyResolver,
            processingDelay: $processingDelay,
            contextFactory: $contextFactory,
        );

        $processor->process($pendingJob);

        $this->assertSame(AsyncJobStatus::Completed, $finalStatus);
        $this->assertSame('reports/sales-outlets-11111111-1111-1111-1111-111111111111.csv', $finalFilePath);
    }

    public function test_process_marks_html_email_job_completed_without_file_path(): void
    {
        $pendingJob = $this->makeJob(AsyncJobStatus::Pending, SalesOutletsReportType::HtmlEmail);
        $processingJob = $this->makeJob(AsyncJobStatus::Processing, SalesOutletsReportType::HtmlEmail);
        $completedJob = $this->makeJob(AsyncJobStatus::Completed, SalesOutletsReportType::HtmlEmail);

        $finalStatus = null;
        $finalFilePath = 'unset';

        $reportRepository = $this->createMock(SalesOutletsAsyncJobRepositoryInterface::class);
        $reportRepository
            ->expects($this->exactly(2))
            ->method('updateStatus')
            ->willReturnCallback(function (
                SalesOutletAsyncJob $job,
                AsyncJobStatus $status,
                ?string $filePath = null,
            ) use ($processingJob, $completedJob, &$finalStatus, &$finalFilePath): SalesOutletAsyncJob {
                $finalStatus = $status;
                $finalFilePath = $filePath;

                return $status === AsyncJobStatus::Processing ? $processingJob : $completedJob;
            });

        $strategy = $this->createProcessingStrategyStub(
            SalesOutletsReportType::HtmlEmail,
            supportsDownload: false,
            buildResult: '<table></table>',
            deliveryResult: ReportDeliveryResult::none(),
        );

        $strategyResolver = $this->createMock(SalesOutletsReportStrategyResolverInterface::class);
        $strategyResolver
            ->expects($this->once())
            ->method('resolve')
            ->with(SalesOutletsReportType::HtmlEmail)
            ->willReturn($strategy);

        $processingDelay = $this->createMock(ReportProcessingDelayInterface::class);
        $processingDelay->expects($this->once())->method('apply');

        $contextFactory = $this->createMock(SalesOutletsReportContextFactoryInterface::class);
        $realContextFactory = new SalesOutletsReportContextFactory;
        $contextFactory
            ->expects($this->once())
            ->method('fromJob')
            ->with($processingJob)
            ->willReturnCallback(fn (SalesOutletAsyncJob $job) => $realContextFactory->fromJob($job));

        $processor = new SalesOutletsReportJobProcessor(
            reportRepository: $reportRepository,
            strategyResolver: $strategyResolver,
            processingDelay: $processingDelay,
            contextFactory: $contextFactory,
        );

        $processor->process($pendingJob);

        $this->assertSame(AsyncJobStatus::Completed, $finalStatus);
        $this->assertNull($finalFilePath);
    }

    private function createProcessingStrategyStub(
        SalesOutletsReportType $reportType,
        bool $supportsDownload,
        string $buildResult = '',
        ReportDeliveryResult $deliveryResult = new ReportDeliveryResult,
    ): SalesOutletsReportProcessingStrategyInterface {
        if ($supportsDownload) {
            return new class($reportType, $buildResult, $deliveryResult) implements SalesOutletsDownloadableReportStrategyInterface
            {
                public function __construct(
                    private readonly SalesOutletsReportType $reportType,
                    private readonly string $buildResult,
                    private readonly ReportDeliveryResult $deliveryResult,
                ) {}

                public function reportType(): SalesOutletsReportType
                {
                    return $this->reportType;
                }

                public function build(SalesOutletReportContextDto $context): string
                {
                    return $this->buildResult;
                }

                public function deliver(SalesOutletAsyncJob $job, string $content): ReportDeliveryResult
                {
                    return $this->deliveryResult;
                }

                public function storagePathForJob(SalesOutletAsyncJob $job): string
                {
                    return 'reports/stub.csv';
                }

                public function downloadFileName(SalesOutletAsyncJob $job): string
                {
                    return 'stub.csv';
                }

                public function downloadContentType(): string
                {
                    return 'text/csv; charset=UTF-8';
                }
            };
        }

        return new class($reportType, $buildResult, $deliveryResult) implements SalesOutletsReportProcessingStrategyInterface
        {
            public function __construct(
                private readonly SalesOutletsReportType $reportType,
                private readonly string $buildResult,
                private readonly ReportDeliveryResult $deliveryResult,
            ) {}

            public function reportType(): SalesOutletsReportType
            {
                return $this->reportType;
            }

            public function build(SalesOutletReportContextDto $context): string
            {
                return $this->buildResult;
            }

            public function deliver(SalesOutletAsyncJob $job, string $content): ReportDeliveryResult
            {
                return $this->deliveryResult;
            }
        };
    }

    private function makeJob(
        AsyncJobStatus $status,
        SalesOutletsReportType $reportType,
        ?string $filePath = null,
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
            filePath: $filePath,
        );
    }
}
