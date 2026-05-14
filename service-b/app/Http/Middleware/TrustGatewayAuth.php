<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;


class TrustGatewayAuth
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->header('X-User-Id');
        if ($userId) {
            $user = \App\Models\User::find($userId);
            if ($user) {
                Auth::guard('api')->login($user);
            }
        }
        return $next($request);
    }
}
