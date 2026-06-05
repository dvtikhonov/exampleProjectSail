<?php

namespace Tests\Unit;

use App\DTO\Auth\GatewayUserDto;
use App\Models\User;
use App\Services\Auth\EloquentGatewayUserResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class EloquentGatewayUserResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_dto_when_user_exists(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/');
        $request->headers->set('X-User-Id', (string) $user->id);

        $dto = (new EloquentGatewayUserResolver)->resolveFromRequest($request);

        $this->assertInstanceOf(GatewayUserDto::class, $dto);
        $this->assertTrue($user->is($dto->user));
    }

    public function test_returns_null_when_header_missing(): void
    {
        $request = Request::create('/');

        $dto = (new EloquentGatewayUserResolver)->resolveFromRequest($request);

        $this->assertNull($dto);
    }

    public function test_returns_null_when_header_empty(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-User-Id', '');

        $dto = (new EloquentGatewayUserResolver)->resolveFromRequest($request);

        $this->assertNull($dto);
    }

    public function test_provisions_user_when_gateway_header_has_unknown_id(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-User-Id', '4242');

        $dto = (new EloquentGatewayUserResolver)->resolveFromRequest($request);

        $this->assertInstanceOf(GatewayUserDto::class, $dto);
        $this->assertSame(4242, $dto->user->id);
        $this->assertDatabaseHas('users', [
            'id' => 4242,
            'email' => 'gateway-user-4242@gateway.local',
        ]);
    }

    public function test_returns_null_when_header_is_not_numeric(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-User-Id', 'abc');

        $dto = (new EloquentGatewayUserResolver)->resolveFromRequest($request);

        $this->assertNull($dto);
    }
}
