<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rate limit при неудачных попытках входа.
 */
class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;

    /** Формирует ключ throttle (совместим с Laravel Breeze). */
    public function throttleKey(string $email, ?string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.($ip ?? ''));
    }

    /**
     * Проверяет, не превышен ли лимит попыток входа.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(string $email, ?string $ip, ?Request $request = null): void
    {
        $key = $this->throttleKey($email, $ip);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        if ($request !== null) {
            event(new Lockout($request));
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /** Увеличивает счётчик при неудачной попытке входа. */
    public function hit(string $email, ?string $ip): void
    {
        RateLimiter::hit($this->throttleKey($email, $ip));
    }

    /** Сбрасывает счётчик после успешного входа. */
    public function clear(string $email, ?string $ip): void
    {
        RateLimiter::clear($this->throttleKey($email, $ip));
    }
}
