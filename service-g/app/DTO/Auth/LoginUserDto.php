<?php

declare(strict_types=1);

namespace App\DTO\Auth;

/**
 * Данные для входа пользователя SPA.
 */
readonly class LoginUserDto
{
    /** @param  bool  $remember  Запомнить сессию (remember me). */
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember,
    ) {}
}
