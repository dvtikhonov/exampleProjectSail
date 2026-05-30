<?php

namespace Tests\Unit;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\Contracts\Auth\GatewayUserResolverInterface;
use App\DTO\Auth\GatewayUserDto;
use App\Http\Middleware\TrustGatewayAuth;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class TrustGatewayAuthTest extends TestCase
{
    public function test_logs_in_when_resolver_returns_dto(): void
    {
        $user = new User;
        $user->forceFill(['id' => 42]);
        $dto = new GatewayUserDto(user: $user);

        $resolver = $this->createMock(GatewayUserResolverInterface::class);
        $resolver->method('resolveFromRequest')->willReturn($dto);

        $session = $this->createMock(GatewayAuthSessionInterface::class);
        $session->expects($this->once())
            ->method('login')
            ->with($dto);

        $middleware = new TrustGatewayAuth($resolver, $session);
        $request = Request::create('/test');

        $response = $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_skips_login_when_resolver_returns_null(): void
    {
        $resolver = $this->createMock(GatewayUserResolverInterface::class);
        $resolver->method('resolveFromRequest')->willReturn(null);

        $session = $this->createMock(GatewayAuthSessionInterface::class);
        $session->expects($this->never())->method('login');

        $middleware = new TrustGatewayAuth($resolver, $session);
        $request = Request::create('/test');

        $middleware->handle($request, fn (Request $req) => response('ok'));

        $this->addToAssertionCount(1);
    }
}
