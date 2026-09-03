<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Shared\ApplicationConfigInterface;
use Shared\MaxMessenger\Config\MaxMessengerRetryConfig;

/**
 * Фабрика конфигурации повторных запросов к MAX Messenger API.
 */
class ConfigMaxMessengerRetryConfigFactory
{
    public function __construct(
        private readonly ApplicationConfigInterface $config,
    ) {}

    /**
     * Создаёт конфигурацию retry из настроек приложения.
     */
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
