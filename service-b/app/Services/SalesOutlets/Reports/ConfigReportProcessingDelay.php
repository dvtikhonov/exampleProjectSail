<?php

namespace App\Services\SalesOutlets\Reports;

use App\Contracts\SalesOutlets\ReportProcessingDelayConfigInterface;
use App\Contracts\SalesOutlets\ReportProcessingDelayInterface;
use App\Enums\SalesOutletsReportType;

class ConfigReportProcessingDelay implements ReportProcessingDelayInterface
{
    public function __construct(
        private readonly ReportProcessingDelayConfigInterface $delayConfig,
    ) {}

    public function apply(SalesOutletsReportType $reportType): void
    {
        if (! $this->delayConfig->applyFakeDelay()) {
            return;
        }

        $delaySeconds = $this->delayConfig->fakeDelaySeconds($reportType);

        if ($delaySeconds > 0) {
            sleep($delaySeconds);
        }
    }
}
