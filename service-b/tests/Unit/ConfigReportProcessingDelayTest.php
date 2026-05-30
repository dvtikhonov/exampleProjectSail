<?php

namespace Tests\Unit;

use App\Contracts\SalesOutlets\ReportProcessingDelayConfigInterface;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\Reports\ConfigReportProcessingDelay;
use PHPUnit\Framework\TestCase;

class ConfigReportProcessingDelayTest extends TestCase
{
    public function test_apply_skips_delay_outside_configured_environments(): void
    {
        $delayConfig = $this->createMock(ReportProcessingDelayConfigInterface::class);
        $delayConfig->method('applyFakeDelay')->willReturn(false);
        $delayConfig->expects($this->never())->method('fakeDelaySeconds');

        (new ConfigReportProcessingDelay($delayConfig))
            ->apply(SalesOutletsReportType::CsvDownload);
    }

    public function test_apply_reads_delay_from_reports_config(): void
    {
        $delayConfig = $this->createMock(ReportProcessingDelayConfigInterface::class);
        $delayConfig->method('applyFakeDelay')->willReturn(true);
        $delayConfig
            ->expects($this->once())
            ->method('fakeDelaySeconds')
            ->with(SalesOutletsReportType::HtmlEmail)
            ->willReturn(0);

        (new ConfigReportProcessingDelay($delayConfig))
            ->apply(SalesOutletsReportType::HtmlEmail);
    }
}
