<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Подписанная заглушка initData для локальной отладки mini-app в браузере (без MAX WebView).
 */
final class MaxLocalDevInitData
{
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

        $userId = (int) config('max.local_dev_user_id', 1002);
        $profile = self::resolveDemoUserProfile($userId);

        if ($profile === null) {
            return null;
        }

        $userPayload = json_encode([
            'id' => $userId,
            'first_name' => $profile['first_name'],
            'last_name' => $profile['last_name'],
            'username' => $profile['username'],
            'language_code' => $profile['language_code'],
            'photo_url' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($userPayload)) {
            return null;
        }

        return MaxWebAppInitDataSigner::sign(
            (string) config('max.bot_access_token'),
            [
                'auth_date' => (string) time(),
                'query_id' => 'local-dev-'.$userId,
                'user' => $userPayload,
            ],
        );
    }

    /**
     * @return array{first_name: string, last_name: string, username: string, language_code: string}|null
     */
    private static function resolveDemoUserProfile(int $userId): ?array
    {
        $profiles = config('max.local_dev_demo_users', []);

        if (! is_array($profiles)) {
            return null;
        }

        $profile = $profiles[$userId] ?? null;

        if (! is_array($profile)) {
            return null;
        }

        $firstName = $profile['first_name'] ?? null;
        $lastName = $profile['last_name'] ?? null;
        $username = $profile['username'] ?? null;
        $languageCode = $profile['language_code'] ?? null;

        if (! is_string($firstName) || ! is_string($lastName) || ! is_string($username) || ! is_string($languageCode)) {
            return null;
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'language_code' => $languageCode,
        ];
    }
}
