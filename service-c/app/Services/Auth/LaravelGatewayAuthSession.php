<?php

namespace App\Services\Auth;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\DTO\Auth\GatewayUserDto;
use Illuminate\Contracts\Auth\Guard;

/**
 * Открытие Laravel-сессии для пользователя gateway.
 */
class LaravelGatewayAuthSession implements GatewayAuthSessionInterface
{
    public function __construct(private readonly Guard $guard) {}

    /**
     * {@inheritDoc}
     */
    public function login(GatewayUserDto $user): void
    {
        $this->guard->login($user->user);
    }
}
