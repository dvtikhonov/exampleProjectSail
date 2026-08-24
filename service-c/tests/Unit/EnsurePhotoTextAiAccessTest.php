<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\DTO\Max\AiAccessStatusDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Http\Middleware\EnsurePhotoTextAiAccess;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsurePhotoTextAiAccessTest extends TestCase
{
    /** Без активного AI-доступа возвращает 403 с сообщением. */
    public function test_returns_forbidden_when_ai_access_disabled(): void
    {
        $middleware = $this->makeMiddleware(
            status: new AiAccessStatusDto(enabled: false, activeMaxUserId: null, expiresAt: null),
            hasMaxManagerRole: false,
        );

        $response = $middleware->handle(
            Request::create('/api/food/phototext/restaurants', 'GET'),
            fn (Request $request) => response('ok'),
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertSame(
            'Доступ AI к базе не разрешён.',
            $response->getData(true)['message'] ?? null,
        );
    }

    /** Активный доступ без роли max_manager возвращает 403. */
    public function test_returns_forbidden_when_active_user_is_not_max_manager(): void
    {
        $middleware = $this->makeMiddleware(
            status: new AiAccessStatusDto(
                enabled: true,
                activeMaxUserId: 1006,
                expiresAt: '2026-08-20T12:30:00+00:00',
            ),
            hasMaxManagerRole: false,
        );

        $response = $middleware->handle(
            Request::create('/api/food/phototext/restaurants', 'GET'),
            fn (Request $request) => response('ok'),
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertSame(
            'Доступ AI к базе не разрешён.',
            $response->getData(true)['message'] ?? null,
        );
    }

    /** Активный AI-доступ max_manager пропускает запрос. */
    public function test_passes_when_active_max_manager_ai_access_exists(): void
    {
        $middleware = $this->makeMiddleware(
            status: new AiAccessStatusDto(
                enabled: true,
                activeMaxUserId: 1006,
                expiresAt: '2026-08-20T12:30:00+00:00',
            ),
            hasMaxManagerRole: true,
        );

        $response = $middleware->handle(
            Request::create('/api/food/phototext/restaurants', 'GET'),
            fn (Request $request) => response('ok', Response::HTTP_OK),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    private function makeMiddleware(
        AiAccessStatusDto $status,
        bool $hasMaxManagerRole,
    ): EnsurePhotoTextAiAccess {
        $aiAccess = Mockery::mock(MaxAiAccessServiceInterface::class);
        $aiAccess->shouldReceive('getStatus')
            ->once()
            ->andReturn($status);

        $admins = Mockery::mock(FoodOrderAdminRepositoryInterface::class);

        if ($status->enabled && $status->activeMaxUserId !== null) {
            $admins->shouldReceive('hasActiveRole')
                ->once()
                ->with($status->activeMaxUserId, FoodOrderAdminRole::MaxManager)
                ->andReturn($hasMaxManagerRole);
        } else {
            $admins->shouldReceive('hasActiveRole')->never();
        }

        return new EnsurePhotoTextAiAccess($aiAccess, $admins);
    }
}
