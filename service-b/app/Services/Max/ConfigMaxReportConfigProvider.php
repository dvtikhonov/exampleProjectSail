<?php

namespace App\Services\Max;

use App\Contracts\Max\MaxReportConfigProviderInterface;
use App\DTO\Max\MaxReportConfig;
use App\Support\Config\SalesOutletsReportsConfigKeys;
use Illuminate\Contracts\Config\Repository;
use RuntimeException;

class ConfigMaxReportConfigProvider implements MaxReportConfigProviderInterface
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function config(): MaxReportConfig
    {
        $chatIds = array_values(array_map(
            intval(...),
            (array) $this->config->get(SalesOutletsReportsConfigKeys::MAX_MESSAGE_CHAT_IDS, []),
        ));
        $userIds = array_values(array_map(
            intval(...),
            (array) $this->config->get(SalesOutletsReportsConfigKeys::MAX_MESSAGE_USER_IDS, []),
        ));

        if ($chatIds === [] && $userIds === []) {
            throw new RuntimeException('MAX report recipients are not configured.');
        }

        return new MaxReportConfig(
            chatIds: $chatIds,
            userIds: $userIds,
            intro: (string) $this->config->get(
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_INTRO,
                '',
            ),
            maxTextLength: (int) $this->config->get(
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_MAX_TEXT_LENGTH,
                4000,
            ),
            rateLimitRetryMax: (int) $this->config->get(
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_RATE_LIMIT_RETRY_MAX,
                2,
            ),
            rateLimitRetryDelayMs: (int) $this->config->get(
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_RATE_LIMIT_RETRY_DELAY_MS,
                500,
            ),
            interRecipientDelayMs: (int) $this->config->get(
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_INTER_RECIPIENT_DELAY_MS,
                50,
            ),
        );
    }
}
