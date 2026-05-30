<?php

namespace App\Services\SalesOutlets\Reports\Strategies;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Contracts\SalesOutlets\ReportFileStorageInterface;
use App\Contracts\SalesOutlets\SalesOutletsDownloadableReportStrategyInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\Reports\AbstractSalesOutletsCsvReportStrategy;
use App\Services\SalesOutlets\SalesOutletColumnSelector;
use Shared\SalesOutletsDomain\AbstractStrategy\CsvReportWriterInterface;

class CsvDownloadReportStrategy extends AbstractSalesOutletsCsvReportStrategy implements SalesOutletsDownloadableReportStrategyInterface
{
    private const DOWNLOAD_CONTENT_TYPE = 'text/csv; charset=UTF-8';

    public function __construct(
        SalesOutletsDataRepositoryInterface $dataRepository,
        SalesOutletColumnSelector $columnSelector,
        CsvReportWriterInterface $csvWriter,
        private readonly ReportFileStorageInterface $fileStorage,
    ) {
        parent::__construct($dataRepository, $columnSelector, $csvWriter);
    }

    public function reportType(): SalesOutletsReportType
    {
        return SalesOutletsReportType::CsvDownload;
    }

    public function storagePathForJob(SalesOutletAsyncJob $job): string
    {
        return 'reports/sales-outlets-'.$job->uuid.'.csv';
    }

    public function downloadFileName(SalesOutletAsyncJob $job): string
    {
        if ($job->userId === null) {
            return 'objects-sales-outlets.csv';
        }

        return 'objects-sales-outlets-'.$job->userId.'.csv';
    }

    public function downloadContentType(): string
    {
        return self::DOWNLOAD_CONTENT_TYPE;
    }

    public function deliver(SalesOutletAsyncJob $job, string $content): ReportDeliveryResult
    {
        $path = $this->storagePathForJob($job);
        $this->fileStorage->put($path, $content);

        return ReportDeliveryResult::withFile($path);
    }
}
