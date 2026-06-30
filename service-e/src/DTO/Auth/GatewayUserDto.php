<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use App\Entity\User;

/** DTO аутентифицированного gateway-пользователя. */
readonly class GatewayUserDto
{
    public function __construct(public User $user)
    {
    }
}
