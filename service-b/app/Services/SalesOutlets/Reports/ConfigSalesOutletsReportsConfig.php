<?php

namespace App\Services\SalesOutlets\Reports;

use App\Contracts\SalesOutlets\ReportProcessingDelayConfigInterface;
use App\Contracts\SalesOutlets\ReportStorageConfigInterface;
use App\Enums\SalesOutletsReportType;
use Illuminate\Contracts\Config\Repository;

final class ConfigSalesOutletsReportsConfig implements ReportProcessingDelayConfigInterface, ReportStorageConfigInterface
{
    public function __construct(
        private readonly Repository $config,
        private readonly string $environment,
    ) {}

    public function storageDisk(): string
    {
        return (string) $this->config->get('sales_outlets_reports.storage_disk', 'local');
    }

    public function fakeDelaySeconds(SalesOutletsReportType $reportType): int
    {
        return (int) $this->config->get(
            'sales_outlets_reports.types.'.$reportType->configKey().'.fake_delay_seconds',
            0,
        );
    }

    public function applyFakeDelay(): bool
    {
        return in_array(
            $this->environment,
            (array) $this->config->get('sales_outlets_reports.apply_fake_delay_environments', []),
            true,
        );
    }
}
