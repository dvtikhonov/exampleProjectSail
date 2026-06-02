<?php

namespace App\Services\SalesOutlets\Reports;

use App\Contracts\SalesOutlets\ReportProcessingDelayConfigInterface;
use App\Contracts\SalesOutlets\ReportStorageConfigInterface;
use App\Enums\SalesOutletsReportType;
use App\Support\Config\SalesOutletsReportsConfigKeys;
use Illuminate\Contracts\Config\Repository;

final class ConfigSalesOutletsReportsConfig implements ReportProcessingDelayConfigInterface, ReportStorageConfigInterface
{
    public function __construct(
        private readonly Repository $config,
        private readonly string $environment,
    ) {}

    public function storageDisk(): string
    {
        return (string) $this->config->get(SalesOutletsReportsConfigKeys::STORAGE_DISK, 'local');
    }

    public function fakeDelaySeconds(SalesOutletsReportType $reportType): int
    {
        return (int) $this->config->get(
            SalesOutletsReportsConfigKeys::fakeDelaySeconds($reportType),
            0,
        );
    }

    public function applyFakeDelay(): bool
    {
        return in_array(
            $this->environment,
            (array) $this->config->get(SalesOutletsReportsConfigKeys::APPLY_FAKE_DELAY_ENVIRONMENTS, []),
            true,
        );
    }
}
