<?php

namespace App\Services\Max;

use App\Support\Config\SalesOutletsReportsConfigKeys;
use Illuminate\Contracts\Config\Repository;
use Shared\MaxMessenger\Config\MaxMessengerRetryConfig;

class ConfigMaxMessengerRetryConfigFactory
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function make(): MaxMessengerRetryConfig
    {
        return new MaxMessengerRetryConfig(
            rateLimitRetryMax: (int) $this->config->get(
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_RATE_LIMIT_RETRY_MAX,
                2,
            ),
            rateLimitRetryDelayMs: (int) $this->config->get(
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_RATE_LIMIT_RETRY_DELAY_MS,
                500,
            ),
            attachmentNotReadyRetryMax: (int) $this->config->get(
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_ATTACHMENT_NOT_READY_RETRY_MAX,
                3,
            ),
            attachmentNotReadyRetryDelayMs: (int) $this->config->get(
                SalesOutletsReportsConfigKeys::MAX_MESSAGE_ATTACHMENT_NOT_READY_RETRY_DELAY_MS,
                200,
            ),
        );
    }
}
