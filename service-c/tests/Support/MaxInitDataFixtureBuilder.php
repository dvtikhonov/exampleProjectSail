<?php

declare(strict_types=1);

namespace Tests\Support;

final class MaxInitDataFixtureBuilder
{
    /**
     * @param  array<string, string>  $extraParams
     */
    public static function build(string $botToken, array $extraParams = []): string
    {
        $params = array_merge([
            'auth_date' => (string) time(),
            'query_id' => '4c0ab423-342b-4e45-aea4-2747dbc500cd',
            'user' => json_encode([
                'id' => 67_890,
                'first_name' => 'Max',
                'last_name' => 'User',
                'username' => null,
                'language_code' => 'ru',
                'photo_url' => null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], $extraParams);

        ksort($params);

        $launchParams = implode("\n", array_map(
            static fn (string $key, string $value): string => $key.'='.$value,
            array_keys($params),
            array_values($params),
        ));

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $hash = hash_hmac('sha256', $launchParams, $secretKey);

        $encodedParams = [];

        foreach ($params as $key => $value) {
            $encodedParams[] = $key.'='.rawurlencode($value);
        }

        $encodedParams[] = 'hash='.$hash;

        return implode('&', $encodedParams);
    }
}
