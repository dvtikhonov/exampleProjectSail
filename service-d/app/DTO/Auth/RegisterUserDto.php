<?php

namespace App\DTO\Auth;

/**
 * Данные для регистрации пользователя SPA.
 */
readonly class RegisterUserDto
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
