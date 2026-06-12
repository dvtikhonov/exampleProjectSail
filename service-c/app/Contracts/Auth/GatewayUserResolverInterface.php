<?php

namespace App\Contracts\Auth;

use App\DTO\Auth\GatewayUserDto;
use Illuminate\Http\Request;

interface GatewayUserResolverInterface
{
    public function resolveFromRequest(Request $request): ?GatewayUserDto;
}
