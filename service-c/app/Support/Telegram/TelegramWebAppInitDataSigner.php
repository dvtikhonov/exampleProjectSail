<?php

declare(strict_types=1);

namespace App\Support\Telegram;

/**
 * Подпись initData для Telegram WebApp (HMAC-SHA256, secret = HMAC(botToken, WebAppData)).
 *
 * Параметр signature в data-check-string не участвует — его добавляют отдельно после подписи.
 */
final class TelegramWebAppInitDataSigner
{
    /**
     * Подписывает строку initData WebApp Telegram.
     *
     * @param  array<string, string>  $params  Параметры с декодированными значениями (без hash и signature).
     */
    public static function sign(string $botToken, array $params): string
    {
        unset($params['hash'], $params['signature']);
        ksort($params);

        $dataCheckString = implode("\n", array_map(
            static fn (string $key, string $value): string => $key.'='.$value,
            array_keys($params),
            array_values($params),
        ));

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $encodedParams = [];

        foreach ($params as $key => $value) {
            $encodedParams[] = $key.'='.rawurlencode($value);
        }

        $encodedParams[] = 'hash='.$hash;

        return implode('&', $encodedParams);
    }
}
