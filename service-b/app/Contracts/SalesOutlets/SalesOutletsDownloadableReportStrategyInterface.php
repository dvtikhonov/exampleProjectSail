<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;

/**
 * Downloadable report strategies: processing + file presentation metadata.
 * SalesOutletsReportStrategyRegistry::supportsDownload() uses instanceof on this type.
 */
interface SalesOutletsDownloadableReportStrategyInterface extends SalesOutletsReportProcessingStrategyInterface
{
    public function storagePathForJob(SalesOutletAsyncJob $job): string;

    public function downloadFileName(SalesOutletAsyncJob $job): string;

    public function downloadContentType(): string;
}
