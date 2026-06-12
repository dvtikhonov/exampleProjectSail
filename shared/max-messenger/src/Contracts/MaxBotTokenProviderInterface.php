<?php

namespace Shared\MaxMessenger\Contracts;

interface MaxBotTokenProviderInterface
{
    public function botAccessToken(): string;
}
