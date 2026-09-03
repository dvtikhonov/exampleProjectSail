<?php

namespace Tests\Unit;

use App\DTO\Auth\GatewayAuthCredentialsDto;
use App\DTO\Auth\GatewayUserDto;
use App\Models\User;
use App\Repositories\Auth\EloquentGatewayUserResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentGatewayUserResolverTest extends TestCase
{
    use RefreshDatabase;

    /** Возвращает DTO, если пользователь существует. */
    public function test_returns_dto_when_user_exists(): void
    {
        $user = User::factory()->create();
        $credentials = new GatewayAuthCredentialsDto(userId: (int) $user->id);

        $dto = (new EloquentGatewayUserResolver)->resolve($credentials);

        $this->assertInstanceOf(GatewayUserDto::class, $dto);
        $this->assertSame((int) $user->id, $dto->id);
        $this->assertSame((string) $user->name, $dto->name);
        $this->assertSame((string) $user->email, $dto->email);
    }

    /** Возвращает null-credentials через factory при отсутствии заголовка. */
    public function test_credentials_factory_returns_null_when_header_missing(): void
    {
        $this->assertNull(GatewayAuthCredentialsDto::tryFromUserIdHeader(null));
    }

    /** Возвращает null-credentials при пустом заголовке. */
    public function test_credentials_factory_returns_null_when_header_empty(): void
    {
        $this->assertNull(GatewayAuthCredentialsDto::tryFromUserIdHeader(''));
    }

    /** Создаёт пользователя, если он не найден. */
    public function test_provisions_user_when_not_found(): void
    {
        $credentials = new GatewayAuthCredentialsDto(userId: 999999);

        $dto = (new EloquentGatewayUserResolver)->resolve($credentials);

        $this->assertInstanceOf(GatewayUserDto::class, $dto);
        $this->assertSame(999999, $dto->id);
        $this->assertDatabaseHas('users', ['id' => 999999]);
    }
}
