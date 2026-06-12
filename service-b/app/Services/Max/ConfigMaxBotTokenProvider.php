<?php

namespace App\Services\Max;

use App\Support\Config\SalesOutletsReportsConfigKeys;
use Illuminate\Contracts\Config\Repository;
use Shared\MaxMessenger\Contracts\MaxBotTokenProviderInterface;

class ConfigMaxBotTokenProvider implements MaxBotTokenProviderInterface
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function botAccessToken(): string
    {
        return (string) $this->config->get(
            SalesOutletsReportsConfigKeys::MAX_MESSAGE_BOT_ACCESS_TOKEN,
            '',
        );
    }
}
