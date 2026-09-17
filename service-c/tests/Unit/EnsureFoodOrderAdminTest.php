<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Http\Middleware\EnsureFoodOrderAdmin;
use App\Models\Max\MaxUser;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsureFoodOrderAdminTest extends TestCase
{
    /** Пропускает запрос, если у пользователя есть единственная требуемая роль. */
    public function test_passes_when_user_has_single_required_role(): void
    {
        $middleware = $this->makeMiddleware(
            maxUserId: 10_003,
            expectedRoleChecks: [
                [FoodOrderAdminRole::AddressReviewer, true],
            ],
        );

        $response = $middleware->handle(
            $this->requestAsMaxUser(10_003),
            fn (Request $request) => response('ok', Response::HTTP_OK),
            FoodOrderAdminRole::AddressReviewer->value,
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    /** Пропускает запрос, если есть хотя бы одна из нескольких требуемых ролей. */
    public function test_passes_when_user_has_one_of_multiple_required_roles(): void
    {
        $middleware = $this->makeMiddleware(
            maxUserId: 10_004,
            expectedRoleChecks: [
                [FoodOrderAdminRole::AddressReviewer, false],
                [FoodOrderAdminRole::CompositionReviewer, true],
            ],
        );

        $response = $middleware->handle(
            $this->requestAsMaxUser(10_004),
            fn (Request $request) => response('ok', Response::HTTP_OK),
            FoodOrderAdminRole::AddressReviewer->value,
            FoodOrderAdminRole::CompositionReviewer->value,
            FoodOrderAdminRole::MenuManager->value,
            FoodOrderAdminRole::MaxManager->value,
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    /** Возвращает 403, если ни одна из требуемых ролей не активна. */
    public function test_returns_forbidden_when_user_has_none_of_required_roles(): void
    {
        $middleware = $this->makeMiddleware(
            maxUserId: 99_001,
            expectedRoleChecks: [
                [FoodOrderAdminRole::AddressReviewer, false],
                [FoodOrderAdminRole::CompositionReviewer, false],
            ],
        );

        $response = $middleware->handle(
            $this->requestAsMaxUser(99_001),
            fn (Request $request) => response('ok'),
            FoodOrderAdminRole::AddressReviewer->value,
            FoodOrderAdminRole::CompositionReviewer->value,
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertSame(
            'Доступ запрещён.',
            $response->getData(true)['message'] ?? null,
        );
    }

    /** Возвращает 400 при неизвестной роли. */
    public function test_returns_bad_request_for_invalid_role(): void
    {
        $repository = Mockery::mock(FoodOrderAdminRepositoryInterface::class);
        $repository->shouldReceive('hasActiveRole')->never();

        $middleware = new EnsureFoodOrderAdmin($repository);

        $response = $middleware->handle(
            $this->requestAsMaxUser(10_003),
            fn (Request $request) => response('ok'),
            'not_a_role',
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(
            'Invalid admin role.',
            $response->getData(true)['message'] ?? null,
        );
    }

    /**
     * @param  list<array{0: FoodOrderAdminRole, 1: bool}>  $expectedRoleChecks
     */
    private function makeMiddleware(int $maxUserId, array $expectedRoleChecks): EnsureFoodOrderAdmin
    {
        $repository = Mockery::mock(FoodOrderAdminRepositoryInterface::class);

        foreach ($expectedRoleChecks as [$role, $result]) {
            $repository->shouldReceive('hasActiveRole')
                ->once()
                ->with($maxUserId, $role)
                ->andReturn($result);
        }

        return new EnsureFoodOrderAdmin($repository);
    }

    private function requestAsMaxUser(int $maxUserId): Request
    {
        $maxUser = new MaxUser;
        $maxUser->max_user_id = $maxUserId;

        $request = Request::create('/api/food/admin/orders', 'GET');
        $request->setUserResolver(static fn (): MaxUser => $maxUser);

        return $request;
    }
}
