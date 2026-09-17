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
 *
 * Доступна только в local/testing. При непустом gateway.auth_secret
 * дополнительно сверяет заголовок X-Gateway-Secret через hash_equals.
 */
class TrustGatewayAuth
{
    public function __construct(
        private readonly GatewayUserResolverInterface $userResolver,
        private readonly GatewayAuthSessionInterface $authSession,
    ) {}

    /**
     * Проверяет окружение, опциональный секрет и доверие к gateway-аутентификации.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment(['local', 'testing'])) {
            return GatewayUnauthorizedResponse::make();
        }

        $expectedSecret = (string) config('gateway.auth_secret', '');
        if ($expectedSecret !== '') {
            $providedSecret = (string) $request->header('X-Gateway-Secret', '');
            if (! hash_equals($expectedSecret, $providedSecret)) {
                return GatewayUnauthorizedResponse::make();
            }
        }

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
