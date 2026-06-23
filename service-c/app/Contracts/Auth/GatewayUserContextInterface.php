<?php

namespace App\Contracts\Auth;

/**
 * Контекст текущего пользователя gateway в рамках HTTP-запроса.
 */
interface GatewayUserContextInterface
{
    /**
     * Возвращает ID аутентифицированного пользователя или null.
     */
    public function currentUserId(): ?int;
}
