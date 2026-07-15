<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Краткое логирование обращений к MAX mini-app (без init_data и токенов).
 */
final class MaxMiniAppAccessLogger
{
    /**
     * Логирует обращение к странице mini-app.
     */
    public function logPageRequest(Request $request): void
    {
        Log::channel('messMax')->info('MAX mini-app page requested', $this->baseContext($request));
    }

    /**
     * Логирует запрос аутентификации mini-app.
     */
    public function logAuthRequest(Request $request, int $statusCode, ?int $maxUserId = null): void
    {
        Log::channel('messMax')->info('MAX mini-app auth requested', [
            ...$this->baseContext($request),
            'init_data_length' => strlen((string) $request->input('init_data', '')),
            'status' => $statusCode,
            'max_user_id' => $maxUserId,
        ]);
    }

    /**
     * Собирает базовый контекст для логов доступа mini-app.
     *
     * @return array<string, mixed>
     */
    private function baseContext(Request $request): array
    {
        return [
            'host' => MaxAppRequestContext::requestHost($request),
            'is_tunnel' => MaxAppRequestContext::isPublicTunnelRequest($request),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 120, '…'),
        ];
    }
}
