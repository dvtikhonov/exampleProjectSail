<?php

declare(strict_types=1);

namespace App\DTO\Auth;

/**
 * Пользователь приложения, аутентифицированный через nginx-gateway.
 */
readonly class GatewayUserDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}
