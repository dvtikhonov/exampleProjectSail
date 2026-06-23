<?php

namespace App\Services\Max;

use Illuminate\Contracts\Config\Repository;
use Shared\MaxMessenger\Contracts\MaxBotTokenProviderInterface;

/**
 * Поставщик токена бота MAX из переменных окружения.
 */
class EnvMaxBotTokenProvider implements MaxBotTokenProviderInterface
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function botAccessToken(): string
    {
        return (string) $this->config->get('max.bot_access_token', '');
    }
}
