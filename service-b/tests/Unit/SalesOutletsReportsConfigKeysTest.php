<?php

namespace Tests\Unit;

use App\Enums\SalesOutletsReportType;
use App\Support\Config\SalesOutletsReportsConfigKeys;
use PHPUnit\Framework\TestCase;

class SalesOutletsReportsConfigKeysTest extends TestCase
{
    public function test_static_keys_match_config_file_structure(): void
    {
        $config = require dirname(__DIR__, 2).'/config/sales_outlets_reports.php';

        $this->assertArrayHasKey('storage_disk', $config);
        $this->assertArrayHasKey('apply_fake_delay_environments', $config);
        $this->assertArrayHasKey('types', $config);
        $this->assertArrayHasKey('html_email', $config['types']);
        $this->assertArrayHasKey('recipients', $config['types']['html_email']);
        $this->assertArrayHasKey('subject', $config['types']['html_email']);
    }

    public function test_fake_delay_key_exists_for_each_report_type(): void
    {
        $config = require dirname(__DIR__, 2).'/config/sales_outlets_reports.php';

        foreach (SalesOutletsReportType::cases() as $reportType) {
            $key = SalesOutletsReportsConfigKeys::fakeDelaySeconds($reportType);
            $configKey = $reportType->configKey();

            $this->assertArrayHasKey($configKey, $config['types']);
            $this->assertArrayHasKey('fake_delay_seconds', $config['types'][$configKey]);
            $this->assertSame(
                "sales_outlets_reports.types.{$configKey}.fake_delay_seconds",
                $key,
            );
        }
    }
}
