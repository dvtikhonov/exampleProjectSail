<?php

namespace Tests\Unit;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\Contracts\Auth\GatewayUserResolverInterface;
use App\DTO\Auth\GatewayAuthCredentialsDto;
use App\DTO\Auth\GatewayUserDto;
use App\Http\Middleware\TrustGatewayAuth;
use App\Http\Responses\GatewayUnauthorizedResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TrustGatewayAuthTest extends TestCase
{
    /** Логинит пользователя, когда resolver вернул DTO. */
    public function test_logs_in_when_resolver_returns_dto(): void
    {
        $dto = new GatewayUserDto(id: 42, name: 'Gateway User 42', email: 'gateway-user-42@gateway.local');

        $resolver = $this->createMock(GatewayUserResolverInterface::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with($this->callback(
                static fn (GatewayAuthCredentialsDto $credentials): bool => $credentials->userId === 42,
            ))
            ->willReturn($dto);

        $session = $this->createMock(GatewayAuthSessionInterface::class);
        $session->expects($this->once())
            ->method('login')
            ->with($dto);

        $middleware = new TrustGatewayAuth($resolver, $session);
        $request = Request::create('/test');
        $request->headers->set('X-User-Id', '42');

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    /** Возвращает 401, когда заголовок отсутствует. */
    public function test_returns_unauthorized_when_header_missing(): void
    {
        $resolver = $this->createMock(GatewayUserResolverInterface::class);
        $resolver->expects($this->never())->method('resolve');

        $session = $this->createMock(GatewayAuthSessionInterface::class);
        $session->expects($this->never())->method('login');

        $middleware = new TrustGatewayAuth($resolver, $session);
        $request = Request::create('/test');

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame(
            json_encode(['message' => GatewayUnauthorizedResponse::MESSAGE], JSON_THROW_ON_ERROR),
            $response->getContent(),
        );
    }

    /** Возвращает 401, когда resolver вернул null. */
    public function test_returns_unauthorized_when_resolver_returns_null(): void
    {
        $resolver = $this->createMock(GatewayUserResolverInterface::class);
        $resolver->method('resolve')->willReturn(null);

        $session = $this->createMock(GatewayAuthSessionInterface::class);
        $session->expects($this->never())->method('login');

        $middleware = new TrustGatewayAuth($resolver, $session);
        $request = Request::create('/test');
        $request->headers->set('X-User-Id', '42');

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame(
            json_encode(['message' => GatewayUnauthorizedResponse::MESSAGE], JSON_THROW_ON_ERROR),
            $response->getContent(),
        );
    }
}
