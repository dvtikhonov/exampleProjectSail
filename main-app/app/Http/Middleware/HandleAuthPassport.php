<?php

namespace App\Http\Middleware;

use App\Services\Auth\PassportTokenVerifier;
use Closure;
use Illuminate\Http\Request;

class HandleAuthPassport
{
    public function __construct(
        private readonly PassportTokenVerifier $tokenVerifier,
    ) {}

    /**
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?? $request->session()->get('passport_token');

        if (! $token) {
            return redirect('http://localhost/login');
        }

        $userId = $this->tokenVerifier->resolveUserId($token);

        if ($userId === null) {
            return redirect('http://localhost/login');
        }

        $request->attributes->set('auth_user_id', $userId);

        return $next($request);
    }
}
