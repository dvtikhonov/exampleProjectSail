<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrustGatewayAuth
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->header('X-User-Id');

        if ($userId !== null) {
            $user = User::query()->find($userId);

            if ($user !== null) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
