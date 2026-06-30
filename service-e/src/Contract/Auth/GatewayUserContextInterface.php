<?php

declare(strict_types=1);

namespace App\Contract\Auth;

/** Доступ к id текущего gateway-пользователя в рамках запроса. */
interface GatewayUserContextInterface
{
    public function currentUserId(): ?int;
}
