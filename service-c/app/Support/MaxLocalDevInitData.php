<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Подписанная заглушка initData для локальной отладки mini-app в браузере (без MAX WebView).
 */
final class MaxLocalDevInitData
{
    private const DEMO_VIP_MAX_USER_ID = 1002;

    /**
     * Проверяет, разрешена ли локальная заглушка initData.
     */
    public static function isEnabled(?Request $request = null): bool
    {
        if (! (bool) config('max.local_dev_init_data', false)) {
            return false;
        }

        if (! in_array((string) config('app.env'), ['local', 'testing'], true)) {
            return false;
        }

        if ((string) config('max.bot_access_token', '') === '') {
            return false;
        }

        if (MaxAppRequestContext::isPublicTunnelRequest($request)) {
            return false;
        }

        return MaxAppRequestContext::isLocalDevelopmentRequest($request);
    }

    /**
     * Формирует подписанную заглушку initData для локальной отладки.
     */
    public static function build(?Request $request = null): ?string
    {
        if (! self::isEnabled($request)) {
            return null;
        }

        $userPayload = json_encode([
            'id' => self::DEMO_VIP_MAX_USER_ID,
            'first_name' => 'Demo',
            'last_name' => 'VIP',
            'username' => 'demo_vip',
            'language_code' => 'ru',
            'photo_url' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($userPayload)) {
            return null;
        }

        return MaxWebAppInitDataSigner::sign(
            (string) config('max.bot_access_token'),
            [
                'auth_date' => (string) time(),
                'query_id' => 'local-dev-'.self::DEMO_VIP_MAX_USER_ID,
                'user' => $userPayload,
            ],
        );
    }
}
