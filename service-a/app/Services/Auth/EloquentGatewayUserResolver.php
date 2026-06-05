<?php

namespace App\Services\Auth;

use App\Contracts\Auth\GatewayUserResolverInterface;
use App\DTO\Auth\GatewayUserDto;
use App\Models\User;
use Illuminate\Http\Request;

class EloquentGatewayUserResolver implements GatewayUserResolverInterface
{
    public function resolveFromRequest(Request $request): ?GatewayUserDto
    {
        $userId = $request->header('X-User-Id');

        if ($userId === null || $userId === '') {
            return null;
        }

        $user = User::query()->find((int) $userId);

        if ($user === null) {
            return null;
        }

        return new GatewayUserDto(user: $user);
    }
}
