<?php

namespace Tests\Unit;

use App\Services\Max\ConfigMaxReportConfigProvider;
use App\Support\Config\SalesOutletsReportsConfigKeys;
use Illuminate\Contracts\Config\Repository;
use RuntimeException;
use Tests\TestCase;

class ConfigMaxReportConfigProviderTest extends TestCase
{
    public function test_returns_config_when_chat_ids_configured(): void
    {
        $provider = new ConfigMaxReportConfigProvider($this->makeConfigRepository(
            chatIds: [123, 456],
            userIds: [],
        ));

        $config = $provider->config();

        $this->assertSame([123, 456], $config->chatIds);
        $this->assertSame([], $config->userIds);
        $this->assertSame('Объекты продаж — отчёт', $config->intro);
        $this->assertSame(4000, $config->maxTextLength);
        $this->assertSame(2, $config->rateLimitRetryMax);
        $this->assertSame(500, $config->rateLimitRetryDelayMs);
        $this->assertSame(50, $config->interRecipientDelayMs);
    }

    public function test_returns_config_when_user_ids_configured(): void
    {
        $provider = new ConfigMaxReportConfigProvider($this->makeConfigRepository(
            chatIds: [],
            userIds: [789],
        ));

        $config = $provider->config();

        $this->assertSame([], $config->chatIds);
        $this->assertSame([789], $config->userIds);
    }

    public function test_throws_when_no_recipients_configured(): void
    {
        $provider = new ConfigMaxReportConfigProvider($this->makeConfigRepository(
            chatIds: [],
            userIds: [],
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MAX report recipients are not configured.');

        $provider->config();
    }

    /**
     * @param  array<int, int>  $chatIds
     * @param  array<int, int>  $userIds
     */
    private function makeConfigRepository(array $chatIds, array $userIds): Repository
    {
        $config = $this->createMock(Repository::class);
        $config->method('get')->willReturnCallback(function (string $key, mixed $default = null) use ($chatIds, $userIds): mixed {
            return match ($key) {
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_CHAT_IDS => $chatIds,
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_USER_IDS => $userIds,
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_INTRO => 'Объекты продаж — отчёт',
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_MAX_TEXT_LENGTH => 4000,
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_RATE_LIMIT_RETRY_MAX => 2,
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_RATE_LIMIT_RETRY_DELAY_MS => 500,
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_INTER_RECIPIENT_DELAY_MS => 50,
                default => $default,
            };
        });

        return $config;
    }
}
