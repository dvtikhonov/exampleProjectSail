<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\WebAuthenticatorInterface;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Реализация web-аутентификации через Laravel Auth facade.
 */
class LaravelWebAuthenticator implements WebAuthenticatorInterface
{
    public function login(User $user): void
    {
        Auth::login($user);
    }

    public function attempt(array $credentials, bool $remember = false): bool
    {
        return Auth::attempt($credentials, $remember);
    }

    public function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
    }
}
