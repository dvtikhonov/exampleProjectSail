<?php

namespace App\Http\Middleware;

use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Token;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBroadcastingPassport
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->session()->get('passport_token');
        $userId = $this->resolveUserId($token);

        if ($userId === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }

    private function resolveUserId(?string $bearerToken): ?int
    {
        if (! $bearerToken) {
            return null;
        }

        try {
            $encoder = new JoseEncoder;
            $parser = new Parser($encoder);
            $jwt = $parser->parse($bearerToken);
            $claims = $jwt->claims();

            if (! $claims->has('jti')) {
                return null;
            }

            $token = Token::find($claims->get('jti'));

            if (! $token || $token->revoked) {
                return null;
            }

            if ($token->expires_at && Carbon::parse($token->expires_at)->isPast()) {
                return null;
            }

            $userId = (int) $token->user_id;

            if ($userId <= 0 || ! User::query()->whereKey($userId)->exists()) {
                return null;
            }

            return $userId;
        } catch (\Throwable) {
            return null;
        }
    }
}
