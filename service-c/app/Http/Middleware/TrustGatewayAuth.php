<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\Contracts\Auth\GatewayUserResolverInterface;
use App\DTO\Auth\GatewayAuthCredentialsDto;
use App\Http\Responses\GatewayUnauthorizedResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доверенная аутентификация пользователя через nginx-gateway.
 */
class TrustGatewayAuth
{
    public function __construct(
        private readonly GatewayUserResolverInterface $userResolver,
        private readonly GatewayAuthSessionInterface $authSession,
    ) {}

    /**
     * Проверяет доверие к аутентификации через gateway.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $credentials = GatewayAuthCredentialsDto::tryFromUserIdHeader(
            $request->header('X-User-Id'),
        );

        if ($credentials === null) {
            return GatewayUnauthorizedResponse::make();
        }

        $dto = $this->userResolver->resolve($credentials);

        if ($dto === null) {
            return GatewayUnauthorizedResponse::make();
        }

        $this->authSession->login($dto);

        return $next($request);
    }
}
