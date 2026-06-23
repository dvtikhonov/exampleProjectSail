<?php

namespace App\DTO\Auth;

use App\Models\User;

/**
 * Пользователь приложения, аутентифицированный через nginx-gateway.
 */
readonly class GatewayUserDto
{
    public function __construct(public User $user) {}
}
