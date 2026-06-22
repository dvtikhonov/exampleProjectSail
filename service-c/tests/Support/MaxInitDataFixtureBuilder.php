<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\MaxWebAppInitDataSigner;

final class MaxInitDataFixtureBuilder
{
    /**
     * @param  array<string, string>  $extraParams
     */
    public static function build(string $botToken, array $extraParams = []): string
    {
        $userPayload = json_encode([
            'id' => 67_890,
            'first_name' => 'Max',
            'last_name' => 'User',
            'username' => null,
            'language_code' => 'ru',
            'photo_url' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return MaxWebAppInitDataSigner::sign($botToken, array_merge([
            'auth_date' => (string) time(),
            'query_id' => '4c0ab423-342b-4e45-aea4-2747dbc500cd',
            'user' => is_string($userPayload) ? $userPayload : '{}',
        ], $extraParams));
    }
}
