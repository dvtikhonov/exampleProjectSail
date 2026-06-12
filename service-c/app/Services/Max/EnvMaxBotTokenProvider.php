<?php

namespace App\Services\Max;

use Illuminate\Contracts\Config\Repository;
use Shared\MaxMessenger\Contracts\MaxBotTokenProviderInterface;

class EnvMaxBotTokenProvider implements MaxBotTokenProviderInterface
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function botAccessToken(): string
    {
        return (string) $this->config->get('max.bot_access_token', '');
    }
}
