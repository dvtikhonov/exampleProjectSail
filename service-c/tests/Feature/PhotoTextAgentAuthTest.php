<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class PhotoTextAgentAuthTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const TOKEN = 'test-phototext-agent-token';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        config(['phototext.agent_token' => self::TOKEN]);
    }

    /** Без заголовка X-PhotoText-Token возвращает 401. */
    public function test_returns_unauthorized_without_token(): void
    {
        $this->getJson('/api/food/phototext/restaurants')
            ->assertUnauthorized();
    }

    /** Неверный токен возвращает 401. */
    public function test_returns_unauthorized_with_wrong_token(): void
    {
        $this->getJson('/api/food/phototext/restaurants', [
            'X-PhotoText-Token' => 'wrong-token',
        ])->assertUnauthorized();
    }

    /** Без активного AI-доступа max_manager возвращает 403. */
    public function test_returns_forbidden_without_active_ai_access(): void
    {
        $this->getJson('/api/food/phototext/restaurants', [
            'X-PhotoText-Token' => self::TOKEN,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ AI к базе не разрешён.');
    }

    /** Просроченный ai_access_until не даёт доступ. */
    public function test_returns_forbidden_when_ai_access_expired(): void
    {
        $now = Carbon::parse('2026-08-20 12:00:00');
        $this->travelTo($now);

        $this->maxManagerWithAiAccess(
            maxUserId: 30_001,
            until: $now->copy()->subMinute(),
        );

        $this->getJson('/api/food/phototext/restaurants', [
            'X-PhotoText-Token' => self::TOKEN,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ AI к базе не разрешён.');
    }

    /** Верный токен и активный AI-доступ max_manager не требуют Bearer mini-app. */
    public function test_valid_token_with_ai_access_does_not_require_miniapp_bearer(): void
    {
        $now = Carbon::parse('2026-08-20 12:00:00');
        $this->travelTo($now);

        $this->maxManagerWithAiAccess(
            maxUserId: 30_002,
            until: $now->copy()->addMinutes(30),
        );

        $response = $this->getJson('/api/food/phototext/restaurants', [
            'X-PhotoText-Token' => self::TOKEN,
        ]);

        $this->assertNotSame(Response::HTTP_UNAUTHORIZED, $response->status());
        $this->assertNotSame(Response::HTTP_FORBIDDEN, $response->status());
    }

    /**
     * Создаёт max_manager с заданным ai_access_until.
     *
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function maxManagerWithAiAccess(int $maxUserId, Carbon $until): array
    {
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'AiAccessManager'.$maxUserId,
                'ai_access_until' => $until,
            ])),
            FoodOrderAdminRole::MaxManager,
        );

        return $auth;
    }
}
