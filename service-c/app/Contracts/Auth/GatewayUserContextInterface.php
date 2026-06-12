<?php

namespace App\Contracts\Auth;

interface GatewayUserContextInterface
{
    public function currentUserId(): ?int;
}
