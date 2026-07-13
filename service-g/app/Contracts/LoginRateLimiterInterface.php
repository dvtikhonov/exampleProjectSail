<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Validation\ValidationException;

interface LoginRateLimiterInterface
{
    /** Формирует ключ throttle (совместим с Laravel Breeze). */
    public function throttleKey(string $email, ?string $ip): string;

    /**
     * Проверяет, не превышен ли лимит попыток входа.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(string $email, ?string $ip): void;

    /** Увеличивает счётчик при неудачной попытке входа. */
    public function hit(string $email, ?string $ip): void;

    /** Сбрасывает счётчик после успешного входа. */
    public function clear(string $email, ?string $ip): void;
}
