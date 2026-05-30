<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ReportFileStorageInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadCapabilityInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadPresentationInterface;
use App\Contracts\SalesOutlets\SalesOutletsReportDownloadServiceInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\Enums\AsyncJobStatus;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesOutletsReportDownloadService implements SalesOutletsReportDownloadServiceInterface
{
    public function __construct(
        private readonly ReportFileStorageInterface $fileStorage,
        private readonly SalesOutletsReportDownloadPresentationInterface $presentation,
        private readonly SalesOutletsReportDownloadCapabilityInterface $downloadCapability,
    ) {}

    public function supportsDownload(SalesOutletAsyncJob $job): bool
    {
        return $this->downloadCapability->supportsDownload($job->reportType);
    }

    public function isDownloadReady(SalesOutletAsyncJob $job): bool
    {
        if (! $this->supportsDownload($job)) {
            return false;
        }

        return $job->status === AsyncJobStatus::Completed
            && $job->filePath !== null
            && $this->fileStorage->exists($job->filePath);
    }

    public function download(SalesOutletAsyncJob $job): StreamedResponse
    {
        if (! $this->isDownloadReady($job)) {
            throw new RuntimeException('Report file is not ready.');
        }

        return $this->fileStorage->download(
            $job->filePath,
            $this->presentation->downloadFileName($job),
            ['Content-Type' => $this->presentation->downloadContentType($job->reportType)],
        );
    }
}
