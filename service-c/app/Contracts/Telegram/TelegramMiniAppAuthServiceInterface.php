<?php

declare(strict_types=1);

namespace App\Contracts\Telegram;

use App\DTO\Telegram\TelegramWebAppInitDataDto;

/**
 * Аутентификация пользователя Telegram Mini App и выдача Sanctum-токена.
 */
interface TelegramMiniAppAuthServiceInterface
{
    /**
     * Создаёт или обновляет пользователя Telegram и выдаёт access token.
     *
     * @return array{token: string, token_type: string, expires_in: int, user: array<string, mixed>}
     */
    public function issueToken(TelegramWebAppInitDataDto $initData): array;
}
