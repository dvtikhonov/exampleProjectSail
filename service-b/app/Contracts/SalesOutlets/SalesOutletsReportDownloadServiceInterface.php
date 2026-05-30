<?php

namespace App\Contracts\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface SalesOutletsReportDownloadServiceInterface
{
    public function supportsDownload(SalesOutletAsyncJob $job): bool;

    public function isDownloadReady(SalesOutletAsyncJob $job): bool;

    public function download(SalesOutletAsyncJob $job): StreamedResponse;
}
