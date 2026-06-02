<?php

namespace Tests\Unit;

use App\Contracts\SalesOutlets\ReportFileStorageInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadCapabilityInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadPresentationInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\SalesOutletsReportDownloadService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesOutletsReportDownloadServiceTest extends TestCase
{
    public function test_supports_download_delegates_to_capability(): void
    {
        $job = $this->makeJob(SalesOutletsReportType::CsvDownload, AsyncJobStatus::Completed, 'reports/test.csv');

        $capability = $this->createMock(SalesOutletsReportDownloadCapabilityInterface::class);
        $capability
            ->expects($this->once())
            ->method('supportsDownload')
            ->with(SalesOutletsReportType::CsvDownload)
            ->willReturn(true);

        $service = new SalesOutletsReportDownloadService(
            fileStorage: $this->createMock(ReportFileStorageInterface::class),
            presentation: $this->createMock(SalesOutletsReportDownloadPresentationInterface::class),
            downloadCapability: $capability,
        );

        $this->assertTrue($service->supportsDownload($job));
    }

    public function test_is_download_ready_returns_false_for_non_downloadable_type(): void
    {
        $job = $this->makeJob(SalesOutletsReportType::HtmlEmail, AsyncJobStatus::Completed, 'reports/test.csv');

        $capability = $this->createMock(SalesOutletsReportDownloadCapabilityInterface::class);
        $capability
            ->expects($this->once())
            ->method('supportsDownload')
            ->with(SalesOutletsReportType::HtmlEmail)
            ->willReturn(false);

        $fileStorage = $this->createMock(ReportFileStorageInterface::class);
        $fileStorage->expects($this->never())->method('exists');

        $service = new SalesOutletsReportDownloadService(
            fileStorage: $fileStorage,
            presentation: $this->createMock(SalesOutletsReportDownloadPresentationInterface::class),
            downloadCapability: $capability,
        );

        $this->assertFalse($service->isDownloadReady($job));
    }

    public function test_is_download_ready_returns_false_when_file_does_not_exist(): void
    {
        $job = $this->makeJob(SalesOutletsReportType::CsvDownload, AsyncJobStatus::Completed, 'reports/missing.csv');

        $capability = $this->createMock(SalesOutletsReportDownloadCapabilityInterface::class);
        $capability->method('supportsDownload')->willReturn(true);

        $fileStorage = $this->createMock(ReportFileStorageInterface::class);
        $fileStorage
            ->expects($this->once())
            ->method('exists')
            ->with('reports/missing.csv')
            ->willReturn(false);

        $service = new SalesOutletsReportDownloadService(
            fileStorage: $fileStorage,
            presentation: $this->createMock(SalesOutletsReportDownloadPresentationInterface::class),
            downloadCapability: $capability,
        );

        $this->assertFalse($service->isDownloadReady($job));
    }

    public function test_is_download_ready_returns_true_when_completed_and_file_exists(): void
    {
        $job = $this->makeJob(SalesOutletsReportType::CsvDownload, AsyncJobStatus::Completed, 'reports/ready.csv');

        $capability = $this->createMock(SalesOutletsReportDownloadCapabilityInterface::class);
        $capability->method('supportsDownload')->willReturn(true);

        $fileStorage = $this->createMock(ReportFileStorageInterface::class);
        $fileStorage
            ->expects($this->once())
            ->method('exists')
            ->with('reports/ready.csv')
            ->willReturn(true);

        $service = new SalesOutletsReportDownloadService(
            fileStorage: $fileStorage,
            presentation: $this->createMock(SalesOutletsReportDownloadPresentationInterface::class),
            downloadCapability: $capability,
        );

        $this->assertTrue($service->isDownloadReady($job));
    }

    public function test_download_throws_when_not_ready(): void
    {
        $job = $this->makeJob(SalesOutletsReportType::CsvDownload, AsyncJobStatus::Processing);

        $capability = $this->createMock(SalesOutletsReportDownloadCapabilityInterface::class);
        $capability->method('supportsDownload')->willReturn(true);

        $fileStorage = $this->createMock(ReportFileStorageInterface::class);
        $fileStorage->expects($this->never())->method('download');

        $service = new SalesOutletsReportDownloadService(
            fileStorage: $fileStorage,
            presentation: $this->createMock(SalesOutletsReportDownloadPresentationInterface::class),
            downloadCapability: $capability,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Report file is not ready.');

        $service->download($job);
    }

    public function test_download_delegates_to_storage_with_presentation_metadata(): void
    {
        $job = $this->makeJob(SalesOutletsReportType::CsvDownload, AsyncJobStatus::Completed, 'reports/ready.csv');

        $capability = $this->createMock(SalesOutletsReportDownloadCapabilityInterface::class);
        $capability->method('supportsDownload')->willReturn(true);

        $fileStorage = $this->createMock(ReportFileStorageInterface::class);
        $fileStorage->method('exists')->willReturn(true);

        $presentation = $this->createMock(SalesOutletsReportDownloadPresentationInterface::class);
        $presentation
            ->expects($this->once())
            ->method('downloadFileName')
            ->with($job)
            ->willReturn('objects-sales-outlets-10.csv');
        $presentation
            ->expects($this->once())
            ->method('downloadContentType')
            ->with(SalesOutletsReportType::CsvDownload)
            ->willReturn('text/csv; charset=UTF-8');

        $streamedResponse = new StreamedResponse;

        $fileStorage
            ->expects($this->once())
            ->method('download')
            ->with(
                'reports/ready.csv',
                'objects-sales-outlets-10.csv',
                ['Content-Type' => 'text/csv; charset=UTF-8'],
            )
            ->willReturn($streamedResponse);

        $service = new SalesOutletsReportDownloadService(
            fileStorage: $fileStorage,
            presentation: $presentation,
            downloadCapability: $capability,
        );

        $this->assertSame($streamedResponse, $service->download($job));
    }

    private function makeJob(
        SalesOutletsReportType $reportType,
        AsyncJobStatus $status,
        ?string $filePath = null,
    ): SalesOutletAsyncJob {
        return new SalesOutletAsyncJob(
            uuid: '11111111-1111-1111-1111-111111111111',
            userId: 10,
            status: $status,
            reportType: $reportType,
            filters: SalesOutletReportFilterDto::fromValidated(
                validated: ['columns' => ['id']],
                allowedColumns: ['id'],
            ),
            filePath: $filePath,
        );
    }
}
