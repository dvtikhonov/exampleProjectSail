<?php

declare(strict_types=1);

namespace App\Contract\Auth;

/** Доступ к id текущего gateway-пользователя в рамках запроса. */
interface GatewayUserContextInterface
{
    /** Возвращает id gateway-пользователя или null, если сессия не установлена. */
    public function currentUserId(): ?int;
}
