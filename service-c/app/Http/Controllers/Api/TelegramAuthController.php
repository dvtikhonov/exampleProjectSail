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
            $diag = $this->debugHashDiagnosis($rawInitData);
            $payload = [
                'sessionId' => '058a74',
                'runId' => 'pre-fix-2',
                'hypothesisId' => 'A-sig-token',
                'location' => 'TelegramAuthController.php:store',
                'message' => 'telegram auth validate failed',
                'data' => array_merge([
                    'reason' => $e->getMessage(),
                ], $diag),
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            $line = json_encode($payload, JSON_UNESCAPED_UNICODE)."\n";
            @file_put_contents(storage_path('logs/debug-058a74.log'), $line, FILE_APPEND);
            @file_put_contents('/home/ddd/exampleProjectSail/.cursor/debug-058a74.log', $line, FILE_APPEND);
            // #endregion

            $diagSummary = sprintf(
                'sig=%s excl_sig=%s incl_sig=%s bot_id=%s keys=%s',
                $diag['has_signature'] ? '1' : '0',
                $diag['match_exclude_signature'] ? '1' : '0',
                $diag['match_include_signature'] ? '1' : '0',
                (string) ($diag['bot_id'] ?? ''),
                implode(',', $diag['param_keys'] ?? []),
            );

            return response()->json([
                // debug: reason + dual-hash diagnosis visible in AuthGate
                'message' => 'Invalid Telegram initData. ['.$e->getMessage().'] {'.$diagSummary.'}',
                'debug_reason' => $e->getMessage(),
                'debug_meta' => $diag,
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

    /**
     * Debug-only: сравнить HMAC при исключении signature и при его включении.
     *
     * @return array<string, mixed>
     */
    private function debugHashDiagnosis(string $rawInitData): array
    {
        $botToken = trim((string) config('telegram.bot_token'));
        $botId = explode(':', $botToken, 2)[0] ?? '';
        $pairs = [];
        $receivedHash = null;

        foreach (explode('&', trim($rawInitData)) as $chunk) {
            if ($chunk === '') {
                continue;
            }
            $parts = explode('=', $chunk, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$key, $value] = $parts;
            $decoded = rawurldecode($value);
            if ($key === 'hash') {
                $receivedHash = $decoded;
                continue;
            }
            $pairs[$key] = $decoded;
        }

        ksort($pairs);
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);

        $withoutSig = $pairs;
        unset($withoutSig['signature']);
        ksort($withoutSig);
        $checkExclude = implode("\n", array_map(
            static fn (string $k, string $v): string => $k.'='.$v,
            array_keys($withoutSig),
            array_values($withoutSig),
        ));
        $hashExclude = hash_hmac('sha256', $checkExclude, $secretKey);

        $checkInclude = implode("\n", array_map(
            static fn (string $k, string $v): string => $k.'='.$v,
            array_keys($pairs),
            array_values($pairs),
        ));
        $hashInclude = hash_hmac('sha256', $checkInclude, $secretKey);

        $authDate = isset($pairs['auth_date']) && ctype_digit($pairs['auth_date'])
            ? (int) $pairs['auth_date']
            : null;

        return [
            'init_data_length' => strlen($rawInitData),
            'param_keys' => array_keys($pairs + ($receivedHash !== null ? ['hash' => true] : [])),
            'has_hash' => is_string($receivedHash) && $receivedHash !== '',
            'has_signature' => isset($pairs['signature']),
            'has_user' => isset($pairs['user']),
            'auth_date' => $authDate,
            'auth_age_seconds' => $authDate !== null ? (time() - $authDate) : null,
            'server_time' => time(),
            'bot_token_len' => strlen($botToken),
            'bot_id' => $botId,
            'match_exclude_signature' => is_string($receivedHash) && hash_equals($receivedHash, $hashExclude),
            'match_include_signature' => is_string($receivedHash) && hash_equals($receivedHash, $hashInclude),
            'received_hash_prefix' => is_string($receivedHash) ? substr($receivedHash, 0, 8) : null,
        ];
    }
}
