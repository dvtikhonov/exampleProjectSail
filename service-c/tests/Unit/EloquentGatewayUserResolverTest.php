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

    /** Возвращает DTO, если пользователь существует. */
    public function test_returns_dto_when_user_exists(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/');
        $request->headers->set('X-User-Id', (string) $user->id);

        $dto = (new EloquentGatewayUserResolver)->resolveFromRequest($request);

        $this->assertInstanceOf(GatewayUserDto::class, $dto);
        $this->assertTrue($user->is($dto->user));
    }

    /** Возвращает null, если заголовок отсутствует. */
    public function test_returns_null_when_header_missing(): void
    {
        $request = Request::create('/');

        $dto = (new EloquentGatewayUserResolver)->resolveFromRequest($request);

        $this->assertNull($dto);
    }

    /** Возвращает null, если заголовок пуст. */
    public function test_returns_null_when_header_empty(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-User-Id', '');

        $dto = (new EloquentGatewayUserResolver)->resolveFromRequest($request);

        $this->assertNull($dto);
    }

    /** Создаёт пользователя, если он не найден. */
    public function test_provisions_user_when_not_found(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-User-Id', '999999');

        $dto = (new EloquentGatewayUserResolver)->resolveFromRequest($request);

        $this->assertInstanceOf(GatewayUserDto::class, $dto);
        $this->assertSame(999999, $dto->user->id);
        $this->assertDatabaseHas('users', ['id' => 999999]);
    }
}
