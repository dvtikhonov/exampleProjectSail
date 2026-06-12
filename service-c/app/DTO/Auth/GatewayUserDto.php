<?php

namespace App\DTO\Auth;

use App\Models\User;

readonly class GatewayUserDto
{
    public function __construct(public User $user) {}
}
