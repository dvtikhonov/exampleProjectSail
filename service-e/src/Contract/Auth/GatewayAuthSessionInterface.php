<?php

declare(strict_types=1);

namespace App\Contract\Auth;

use App\DTO\Auth\GatewayUserDto;

/** Сохраняет аутентифицированного gateway-пользователя в контексте запроса. */
interface GatewayAuthSessionInterface
{
    public function login(GatewayUserDto $user): void;
}
