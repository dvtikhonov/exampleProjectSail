<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Telegram\TelegramMiniAppAuthServiceInterface;
use App\Contracts\Telegram\TelegramWebAppInitDataValidatorInterface;
use App\Exceptions\Telegram\TelegramWebAppInitDataException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Telegram\ValidateTelegramInitDataRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Аутентификация Telegram Mini App по initData и выдача Bearer-токена.
 */
class TelegramAuthController extends Controller
{
    public function __construct(
        private readonly TelegramWebAppInitDataValidatorInterface $initDataValidator,
        private readonly TelegramMiniAppAuthServiceInterface $authService,
    ) {}

    /**
     * Проверяет initData и возвращает access token Sanctum (ability telegram-miniapp).
     */
    public function store(ValidateTelegramInitDataRequest $request): JsonResponse
    {
        $rawInitData = $request->initData();

        try {
            $initData = $this->initDataValidator->validate($rawInitData);
        } catch (TelegramWebAppInitDataException $e) {
            // #region agent log
            $params = [];
            parse_str($rawInitData, $params);
            $authDate = isset($params['auth_date']) && ctype_digit((string) $params['auth_date'])
                ? (int) $params['auth_date']
                : null;
            $payload = [
                'sessionId' => '058a74',
                'runId' => 'pre-fix',
                'hypothesisId' => 'A-B-C',
                'location' => 'TelegramAuthController.php:store',
                'message' => 'telegram auth validate failed',
                'data' => [
                    'reason' => $e->getMessage(),
                    'init_data_length' => strlen($rawInitData),
                    'param_keys' => array_keys($params),
                    'has_hash' => isset($params['hash']),
                    'has_signature' => isset($params['signature']),
                    'has_user' => isset($params['user']),
                    'auth_date' => $authDate,
                    'auth_age_seconds' => $authDate !== null ? (time() - $authDate) : null,
                    'server_time' => time(),
                    'bot_token_len' => strlen((string) config('telegram.bot_token')),
                ],
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            $line = json_encode($payload, JSON_UNESCAPED_UNICODE)."\n";
            @file_put_contents(storage_path('logs/debug-058a74.log'), $line, FILE_APPEND);
            @file_put_contents('/home/ddd/exampleProjectSail/.cursor/debug-058a74.log', $line, FILE_APPEND);
            // #endregion

            return response()->json([
                // debug: reason in message so UI shows it even without new frontend build
                'message' => 'Invalid Telegram initData. ['.$e->getMessage().']',
                'debug_reason' => $e->getMessage(),
                'debug_meta' => [
                    'init_data_length' => strlen($rawInitData),
                    'param_keys' => array_keys($params),
                    'has_hash' => isset($params['hash']),
                    'has_signature' => isset($params['signature']),
                    'auth_age_seconds' => $authDate !== null ? (time() - $authDate) : null,
                    'bot_token_len' => strlen((string) config('telegram.bot_token')),
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        // #region agent log
        $okLine = json_encode([
            'sessionId' => '058a74',
            'runId' => 'pre-fix',
            'hypothesisId' => 'A-B-C',
            'location' => 'TelegramAuthController.php:store',
            'message' => 'telegram auth validate ok',
            'data' => [
                'telegram_user_id' => $initData->telegramUserId,
                'bot_token_len' => strlen((string) config('telegram.bot_token')),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE)."\n";
        @file_put_contents(storage_path('logs/debug-058a74.log'), $okLine, FILE_APPEND);
        @file_put_contents('/home/ddd/exampleProjectSail/.cursor/debug-058a74.log', $okLine, FILE_APPEND);
        // #endregion

        return response()->json($this->authService->issueToken($initData));
    }
}
