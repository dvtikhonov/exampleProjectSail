<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Max\MaxUser;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Аутентификация запросов MAX mini-app по Sanctum Bearer-токену.
 */
class AuthenticateMaxMiniApp
{
    private const TOKEN_ABILITY = 'max-miniapp';

    /**
     * Минимальный интервал обновления last_used_at (сек).
     */
    private const LAST_USED_AT_THROTTLE_SECONDS = 60;

    /**
     * Аутентифицирует запрос MAX mini-app по Bearer-токену.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return $this->unauthorized();
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if ($accessToken === null) {
            return $this->unauthorized();
        }

        if ($accessToken->expires_at !== null && $accessToken->expires_at->isPast()) {
            return $this->unauthorized();
        }

        if (! $accessToken->can(self::TOKEN_ABILITY)) {
            return $this->unauthorized();
        }

        $maxUser = $accessToken->tokenable;

        if (! $maxUser instanceof MaxUser) {
            return $this->unauthorized();
        }

        $this->touchLastUsedAtThrottled($accessToken);
        $maxUser->withAccessToken($accessToken);
        $request->setUserResolver(static fn (): MaxUser => $maxUser);

        return $next($request);
    }

    /**
     * Обновляет last_used_at не чаще чем раз в LAST_USED_AT_THROTTLE_SECONDS.
     */
    private function touchLastUsedAtThrottled(PersonalAccessToken $accessToken): void
    {
        $lastUsedAt = $accessToken->last_used_at;

        if (
            $lastUsedAt !== null
            && $lastUsedAt->gt(now()->subSeconds(self::LAST_USED_AT_THROTTLE_SECONDS))
        ) {
            return;
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();
    }

    /**
     * Возвращает JSON-ответ об отсутствии аутентификации.
     */
    private function unauthorized(): Response
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
