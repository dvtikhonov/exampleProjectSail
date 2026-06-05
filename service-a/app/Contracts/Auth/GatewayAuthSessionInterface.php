<?php

namespace App\Contracts\Auth;

use App\DTO\Auth\GatewayUserDto;

interface GatewayAuthSessionInterface
{
    public function login(GatewayUserDto $user): void;
}
