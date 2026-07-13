<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Управление HTTP-сессией без привязки сервисного слоя к Request.
 */
interface SessionManagerInterface
{
    /** Регенерирует id сессии после успешного входа/регистрации. */
    public function regenerate(): void;

    /** Инвалидирует сессию при выходе. */
    public function invalidate(): void;

    /** Регенерирует CSRF-токен после выхода. */
    public function regenerateToken(): void;
}
