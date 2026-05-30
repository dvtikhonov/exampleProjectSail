<?php

namespace App\Services\Auth;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\DTO\Auth\GatewayUserDto;
use Illuminate\Contracts\Auth\Guard;

class LaravelGatewayAuthSession implements GatewayAuthSessionInterface
{
    public function __construct(private readonly Guard $guard) {}

    public function login(GatewayUserDto $user): void
    {
        $this->guard->login($user->user);
    }
}
