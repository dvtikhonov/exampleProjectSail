<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\MaxUser;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMaxMiniApp
{
    private const TOKEN_ABILITY = 'max-miniapp';

    /**
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

        $accessToken->forceFill(['last_used_at' => now()])->save();
        $maxUser->withAccessToken($accessToken);
        $request->setUserResolver(static fn (): MaxUser => $maxUser);

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
