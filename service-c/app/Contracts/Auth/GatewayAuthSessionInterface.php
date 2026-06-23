<?php

namespace App\Contracts\Auth;

use App\DTO\Auth\GatewayUserDto;

/**
 * Открытие сессии пользователя, аутентифицированного через nginx-gateway.
 */
interface GatewayAuthSessionInterface
{
    /**
     * Выполняет вход пользователя в guard приложения.
     */
    public function login(GatewayUserDto $user): void;
}
