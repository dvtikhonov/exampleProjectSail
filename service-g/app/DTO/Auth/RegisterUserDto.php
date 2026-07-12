<?php

declare(strict_types=1);

namespace App\DTO\Auth;

/**
 * Данные для регистрации пользователя SPA.
 */
readonly class RegisterUserDto
{
    /** @param  string  $password  Пароль в открытом виде (хешируется в репозитории). */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
