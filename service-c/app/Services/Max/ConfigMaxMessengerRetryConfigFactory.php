<?php

namespace App\Services\Max;

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
            rateLimitRetryMax: (int) $this->config->get('max.rate_limit_retry_max', 2),
            rateLimitRetryDelayMs: (int) $this->config->get('max.rate_limit_retry_delay_ms', 500),
            attachmentNotReadyRetryMax: (int) $this->config->get('max.attachment_not_ready_retry_max', 3),
            attachmentNotReadyRetryDelayMs: (int) $this->config->get('max.attachment_not_ready_retry_delay_ms', 200),
        );
    }
}
