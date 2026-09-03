<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Shared\ApplicationConfigInterface;
use Shared\MaxMessenger\Contracts\MaxBotTokenProviderInterface;

/**
 * Поставщик токена бота MAX из переменных окружения.
 */
class EnvMaxBotTokenProvider implements MaxBotTokenProviderInterface
{
    public function __construct(
        private readonly ApplicationConfigInterface $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function botAccessToken(): string
    {
        return (string) $this->config->get('max.bot_access_token', '');
    }
}
