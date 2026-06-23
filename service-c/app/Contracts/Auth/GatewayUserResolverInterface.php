<?php

namespace App\Contracts\Auth;

use App\DTO\Auth\GatewayUserDto;
use Illuminate\Http\Request;

/**
 * Разрешение пользователя gateway из заголовков HTTP-запроса.
 */
interface GatewayUserResolverInterface
{
    /**
     * Извлекает пользователя из X-User-Id или возвращает null.
     */
    public function resolveFromRequest(Request $request): ?GatewayUserDto;
}
