<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxWebAppInitDataDto;

/**
 * Аутентификация пользователя MAX mini-app и выдача Sanctum-токена.
 */
interface MaxMiniAppAuthServiceInterface
{
    /**
     * Создаёт или обновляет пользователя MAX и выдаёт access token.
     *
     * @return array{token: string, token_type: string, expires_in: int, user: array<string, mixed>}
     */
    public function issueToken(MaxWebAppInitDataDto $initData): array;
}
