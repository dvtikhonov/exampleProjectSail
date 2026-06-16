<?php

namespace App\Services\Auth;

use App\DTO\Auth\LoginUserDto;
use App\DTO\Auth\RegisterUserDto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Аутентификация SPA через Sanctum (cookie + session).
 */
class AuthService
{
    /**
     * Регистрирует пользователя и открывает сессию.
     */
    public function register(RegisterUserDto $dto, Request $request): User
    {
        $user = User::query()->create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return $user;
    }

    /**
     * Выполняет вход по email/password.
     *
     * @throws ValidationException
     */
    public function login(LoginUserDto $dto, Request $request): User
    {
        $this->ensureLoginIsNotRateLimited($dto->email, $request->ip());

        if (! Auth::attempt(
            ['email' => $dto->email, 'password' => $dto->password],
            $dto->remember,
        )) {
            RateLimiter::hit($this->loginThrottleKey($dto->email, $request->ip()));

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->loginThrottleKey($dto->email, $request->ip()));
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Завершает сессию пользователя.
     */
    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * @throws ValidationException
     */
    private function ensureLoginIsNotRateLimited(string $email, ?string $ip): void
    {
        $key = $this->loginThrottleKey($email, $ip);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function loginThrottleKey(string $email, ?string $ip): string
    {
        return strtolower($email).'|'.($ip ?? '');
    }
}
