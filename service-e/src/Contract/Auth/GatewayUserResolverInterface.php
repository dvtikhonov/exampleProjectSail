<?php

declare(strict_types=1);

namespace App\Contract\Auth;

use App\DTO\Auth\GatewayUserDto;
use Symfony\Component\HttpFoundation\Request;

/** Разрешает пользователя gateway из HTTP-запроса (заголовок X-User-Id). */
interface GatewayUserResolverInterface
{
    /** Возвращает DTO пользователя или null, если заголовок отсутствует или невалиден. */
    public function resolveFromRequest(Request $request): ?GatewayUserDto;
}
