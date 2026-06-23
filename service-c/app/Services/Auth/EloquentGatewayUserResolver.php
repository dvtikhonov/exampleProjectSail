<?php

namespace App\Services\Auth;

use App\Contracts\Auth\GatewayUserResolverInterface;
use App\DTO\Auth\GatewayUserDto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Разрешение пользователя gateway по заголовку X-User-Id.
 */
class EloquentGatewayUserResolver implements GatewayUserResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public function resolveFromRequest(Request $request): ?GatewayUserDto
    {
        $userId = $request->header('X-User-Id');

        if ($userId === null || $userId === '' || ! is_numeric($userId) || (int) $userId <= 0) {
            return null;
        }

        $userId = (int) $userId;
        $user = User::query()->find($userId) ?? $this->provisionGatewayUser($userId);

        return new GatewayUserDto(user: $user);
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
