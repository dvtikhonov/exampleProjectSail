<?php

namespace App\DTO\Auth;

/**
 * Данные для входа пользователя SPA.
 */
readonly class LoginUserDto
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember,
    ) {}
}
