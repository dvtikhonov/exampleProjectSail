<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Telegram\TelegramWebAppInitDataSigner;

final class TelegramInitDataFixtureBuilder
{
    /**
     * @param  array<string, string>  $extraParams
     */
    public static function build(string $botToken, array $extraParams = []): string
    {
        $userPayload = json_encode([
            'id' => 67_890,
            'first_name' => 'Telegram',
            'last_name' => 'User',
            'username' => 'tg_user',
            'language_code' => 'ru',
            'photo_url' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return TelegramWebAppInitDataSigner::sign($botToken, array_merge([
            'auth_date' => (string) time(),
            'query_id' => 'AAEAAAEAAAEAAAEAAAEAAAEA',
            'user' => is_string($userPayload) ? $userPayload : '{}',
        ], $extraParams));
    }
}
