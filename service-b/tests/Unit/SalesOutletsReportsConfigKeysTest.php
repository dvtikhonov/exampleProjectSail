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
        $this->assertArrayHasKey('max_message', $config['types']);
        $this->assertArrayHasKey('bot_access_token', $config['types']['max_message']);
        $this->assertArrayHasKey('chat_ids', $config['types']['max_message']);
        $this->assertArrayHasKey('user_ids', $config['types']['max_message']);
        $this->assertArrayHasKey('intro', $config['types']['max_message']);
        $this->assertArrayHasKey('max_text_length', $config['types']['max_message']);
        $this->assertArrayHasKey('api_rate_limit_rps', $config['types']['max_message']);
        $this->assertArrayHasKey('rate_limit_retry_max', $config['types']['max_message']);
        $this->assertArrayHasKey('rate_limit_retry_delay_ms', $config['types']['max_message']);
        $this->assertArrayHasKey('inter_recipient_delay_ms', $config['types']['max_message']);
    }

    public function test_max_message_config_keys_match_structure(): void
    {
        $this->assertSame(
            'sales_outlets_reports.types.max_message.bot_access_token',
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_BOT_ACCESS_TOKEN,
        );
        $this->assertSame(
            'sales_outlets_reports.types.max_message.chat_ids',
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_CHAT_IDS,
        );
        $this->assertSame(
            'sales_outlets_reports.types.max_message.user_ids',
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_USER_IDS,
        );
        $this->assertSame(
            'sales_outlets_reports.types.max_message.intro',
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_INTRO,
        );
        $this->assertSame(
            'sales_outlets_reports.types.max_message.max_text_length',
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_MAX_TEXT_LENGTH,
        );
        $this->assertSame(
            'sales_outlets_reports.types.max_message.api_rate_limit_rps',
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_API_RATE_LIMIT_RPS,
        );
        $this->assertSame(
            'sales_outlets_reports.types.max_message.rate_limit_retry_max',
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_RATE_LIMIT_RETRY_MAX,
        );
        $this->assertSame(
            'sales_outlets_reports.types.max_message.rate_limit_retry_delay_ms',
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_RATE_LIMIT_RETRY_DELAY_MS,
        );
        $this->assertSame(
            'sales_outlets_reports.types.max_message.inter_recipient_delay_ms',
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_INTER_RECIPIENT_DELAY_MS,
        );
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
