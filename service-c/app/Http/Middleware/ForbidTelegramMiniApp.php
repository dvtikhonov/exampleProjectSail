<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Max\MaxUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Блокирует доступ к admin/SchedulePhoto для токенов только с ability telegram-miniapp.
 */
class ForbidTelegramMiniApp
{
    private const TELEGRAM_ABILITY = 'telegram-miniapp';

    private const MAX_ABILITY = 'max-miniapp';

    /**
     * Отклоняет запрос, если Bearer-токен — клиентский Telegram Mini App.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxUser = $request->user();

        if (! $maxUser instanceof MaxUser) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $accessToken = $maxUser->currentAccessToken();

        if ($accessToken === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $isTelegramOnly = $accessToken->can(self::TELEGRAM_ABILITY)
            && ! $accessToken->can(self::MAX_ABILITY);

        if ($isTelegramOnly) {
            return response()->json([
                'message' => 'Telegram Mini App token cannot access admin endpoints.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
