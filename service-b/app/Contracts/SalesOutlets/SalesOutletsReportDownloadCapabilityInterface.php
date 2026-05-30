<?php

namespace App\Contracts\SalesOutlets;

use App\Enums\SalesOutletsReportType;

interface SalesOutletsReportDownloadCapabilityInterface
{
    public function supportsDownload(SalesOutletsReportType $type): bool;
}
