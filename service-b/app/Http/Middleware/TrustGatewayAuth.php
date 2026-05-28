<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TrustGatewayAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->header('X-User-Id');
        if ($userId && Schema::hasTable('users')) {
            $user = User::find($userId);
            if ($user) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
