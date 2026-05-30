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

    public function test_returns_null_when_user_not_found(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-User-Id', '999999');

        $dto = (new EloquentGatewayUserResolver)->resolveFromRequest($request);

        $this->assertNull($dto);
    }
}
