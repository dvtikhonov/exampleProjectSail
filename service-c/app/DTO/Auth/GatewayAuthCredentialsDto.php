<?php

declare(strict_types=1);

namespace App\DTO\Auth;

/**
 * Учётные данные gateway-аутентификации, собранные из HTTP-заголовков.
 */
readonly class GatewayAuthCredentialsDto
{
    public function __construct(
        public int $userId,
    ) {}

    /**
     * Собирает DTO из значения заголовка X-User-Id или возвращает null.
     */
    public static function tryFromUserIdHeader(?string $userIdHeader): ?self
    {
        if ($userIdHeader === null || $userIdHeader === '' || ! is_numeric($userIdHeader)) {
            return null;
        }

        $userId = (int) $userIdHeader;

        if ($userId <= 0) {
            return null;
        }

        return new self(userId: $userId);
    }
}
