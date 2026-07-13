<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\SessionManagerInterface;

/**
 * Реализация управления сессией через Laravel session store.
 */
class LaravelSessionManager implements SessionManagerInterface
{
    public function regenerate(): void
    {
        session()->regenerate();
    }

    public function invalidate(): void
    {
        session()->invalidate();
    }

    public function regenerateToken(): void
    {
        session()->regenerateToken();
    }
}
