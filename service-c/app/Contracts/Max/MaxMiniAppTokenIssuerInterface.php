<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use DateTimeInterface;

/**
 * Выдача и отзыв Sanctum-токенов MAX mini-app без Eloquent в use-case слое.
 */
interface MaxMiniAppTokenIssuerInterface
{
    /**
     * Удаляет все токены с указанным именем у пользователя.
     */
    public function revokeNamedTokens(int $maxUserId, string $tokenName): void;

    /**
     * Создаёт Sanctum-токен и возвращает plain-text значение.
     *
     * @param  list<string>  $abilities
     */
    public function createToken(
        int $maxUserId,
        string $tokenName,
        array $abilities,
        DateTimeInterface $expiresAt,
    ): string;
}
