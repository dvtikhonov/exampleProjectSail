<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

/**
 * Аутентификация через web-guard (Sanctum SPA / session).
 */
interface WebAuthenticatorInterface
{
    /** Открывает сессию для пользователя. */
    public function login(User $user): void;

    /**
     * Пытается аутентифицировать по credentials.
     *
     * @param  array{email: string, password: string}  $credentials
     */
    public function attempt(array $credentials, bool $remember = false): bool;

    /** Текущий аутентифицированный пользователь или null. */
    public function currentUser(): ?User;

    /** Завершает web-сессию пользователя. */
    public function logout(): void;
}
