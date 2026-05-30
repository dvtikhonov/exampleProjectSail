<?php

namespace App\Http\Middleware;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\Contracts\Auth\GatewayUserResolverInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustGatewayAuth
{
    public function __construct(
        private readonly GatewayUserResolverInterface $userResolver,
        private readonly GatewayAuthSessionInterface $authSession,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $dto = $this->userResolver->resolveFromRequest($request);

        if ($dto !== null) {
            $this->authSession->login($dto);
        }

        return $next($request);
    }
}
