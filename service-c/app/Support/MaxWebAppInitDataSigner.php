<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Подпись initData для MAX WebApp (алгоритм как у Telegram WebApp).
 */
final class MaxWebAppInitDataSigner
{
    /**
     * Подписывает строку initData WebApp MAX.
     *
     * @param  array<string, string>  $params  Параметры с декодированными значениями (без hash).
     */
    public static function sign(string $botToken, array $params): string
    {
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
