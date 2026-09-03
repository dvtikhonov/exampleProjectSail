<?php

declare(strict_types=1);

namespace App\Contracts\Auth;

use App\DTO\Auth\GatewayAuthCredentialsDto;
use App\DTO\Auth\GatewayUserDto;

/**
 * Разрешение пользователя gateway из учётных данных HTTP-запроса.
 */
interface GatewayUserResolverInterface
{
    /**
     * Извлекает пользователя по gateway-credentials или возвращает null.
     */
    public function resolve(GatewayAuthCredentialsDto $credentials): ?GatewayUserDto;
}
