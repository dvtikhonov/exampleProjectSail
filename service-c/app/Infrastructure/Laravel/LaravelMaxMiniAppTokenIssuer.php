<?php

declare(strict_types=1);

namespace App\Infrastructure\Laravel;

use App\Contracts\Max\MaxMiniAppTokenIssuerInterface;
use App\Models\Max\MaxUser;
use DateTimeInterface;
use RuntimeException;

/**
 * Laravel/Sanctum-адаптер выдачи токенов MAX mini-app.
 */
final class LaravelMaxMiniAppTokenIssuer implements MaxMiniAppTokenIssuerInterface
{
    /**
     * {@inheritDoc}
     */
    public function revokeNamedTokens(int $maxUserId, string $tokenName): void
    {
        $maxUser = MaxUser::query()->find($maxUserId);

        if ($maxUser === null) {
            throw new RuntimeException(sprintf('MaxUser %d not found for token revoke.', $maxUserId));
        }

        $maxUser->tokens()->where('name', $tokenName)->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function createToken(
        int $maxUserId,
        string $tokenName,
        array $abilities,
        DateTimeInterface $expiresAt,
    ): string {
        $maxUser = MaxUser::query()->find($maxUserId);

        if ($maxUser === null) {
            throw new RuntimeException(sprintf('MaxUser %d not found for token create.', $maxUserId));
        }

        return $maxUser->createToken(
            $tokenName,
            $abilities,
            $expiresAt,
        )->plainTextToken;
    }
}
