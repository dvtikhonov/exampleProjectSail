<?php

declare(strict_types=1);

namespace App\Repositories\Auth;

use App\Contracts\Auth\GatewayUserResolverInterface;
use App\DTO\Auth\GatewayAuthCredentialsDto;
use App\DTO\Auth\GatewayUserDto;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Разрешение пользователя gateway по идентификатору из credentials.
 */
class EloquentGatewayUserResolver implements GatewayUserResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public function resolve(GatewayAuthCredentialsDto $credentials): ?GatewayUserDto
    {
        $user = User::query()->find($credentials->userId)
            ?? $this->provisionGatewayUser($credentials->userId);

        return new GatewayUserDto(
            id: (int) $user->id,
            name: (string) $user->name,
            email: (string) $user->email,
        );
    }

    /**
     * Создаёт пользователя gateway при первом обращении.
     */
    private function provisionGatewayUser(int $userId): User
    {
        return User::query()->forceCreate([
            'id' => $userId,
            'name' => "Gateway User {$userId}",
            'email' => "gateway-user-{$userId}@gateway.local",
            'password' => Hash::make(Str::random(32)),
        ]);
    }
}
