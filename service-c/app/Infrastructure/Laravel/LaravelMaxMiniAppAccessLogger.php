<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Max\MaxMiniAppAccessLoggerInterface;
use App\DTO\Max\MaxMiniAppAccessContextDto;
use Psr\Log\LoggerInterface;

/**
 * Laravel-адаптер логирования доступа MAX mini-app через канал max_log.
 */
final class LaravelMaxMiniAppAccessLogger implements MaxMiniAppAccessLoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function logPageRequest(MaxMiniAppAccessContextDto $context): void
    {
        $this->logger->info('MAX mini-app page requested', $this->baseContext($context));
    }

    /**
     * {@inheritDoc}
     */
    public function logAuthRequest(
        MaxMiniAppAccessContextDto $context,
        int $statusCode,
        ?int $maxUserId = null,
    ): void {
        $this->logger->info('MAX mini-app auth requested', [
            ...$this->baseContext($context),
            'init_data_length' => $context->initDataLength ?? 0,
            'status' => $statusCode,
            'max_user_id' => $maxUserId,
        ]);
    }

    /**
     * Собирает базовый контекст для логов доступа mini-app.
     *
     * @return array{host: string, is_tunnel: bool, ip: ?string, user_agent: string}
     */
    private function baseContext(MaxMiniAppAccessContextDto $context): array
    {
        return [
            'host' => $context->host,
            'is_tunnel' => $context->isTunnel,
            'ip' => $context->ip,
            'user_agent' => $context->userAgent,
        ];
    }
}
