<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\DTO\Auth\GatewayUserDto;
use App\Models\User;
use Illuminate\Contracts\Auth\Guard;
use RuntimeException;

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
        $eloquentUser = User::query()->find($user->id);

        if ($eloquentUser === null) {
            throw new RuntimeException("Gateway user {$user->id} not found for session login.");
        }

        $this->guard->login($eloquentUser);
    }
}
