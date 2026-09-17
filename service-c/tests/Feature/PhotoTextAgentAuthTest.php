<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ConfiguresPhotoTextAgent;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class PhotoTextAgentAuthTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use ConfiguresPhotoTextAgent;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string AGENT_TOKEN = 'test-phototext-agent-token';

    private const string WRITE_TOKEN = 'test-phototext-write-token';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        config([
            'phototext.agent_token' => self::AGENT_TOKEN,
            'phototext.write_token' => self::WRITE_TOKEN,
        ]);
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
        $this->getJson('/api/food/phototext/restaurants', $this->photoTextHeaders())
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

        $this->getJson('/api/food/phototext/restaurants', $this->photoTextHeaders())
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

        $response = $this->getJson('/api/food/phototext/restaurants', $this->photoTextHeaders());

        $this->assertNotSame(Response::HTTP_UNAUTHORIZED, $response->status());
        $this->assertNotSame(Response::HTTP_FORBIDDEN, $response->status());
    }

    /** 31-й POST за минуту при валидном токене и AI-доступе возвращает 429. */
    public function test_post_requests_are_throttled_after_thirty_per_minute(): void
    {
        $now = Carbon::parse('2026-08-20 12:00:00');
        $this->travelTo($now);

        $this->maxManagerWithAiAccess(
            maxUserId: 30_003,
            until: $now->copy()->addMinutes(30),
        );

        $headers = $this->photoTextHeaders();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $response = $this->postJson('/api/food/phototext/match', [], $headers);

            $this->assertNotSame(
                Response::HTTP_TOO_MANY_REQUESTS,
                $response->status(),
                "Attempt {$attempt} should not be throttled.",
            );
        }

        $this->postJson('/api/food/phototext/match', [], $headers)
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);
    }

    /** Place/apply без X-PhotoText-Write-Token возвращают 401. */
    public function test_mutations_return_unauthorized_without_write_token(): void
    {
        $now = Carbon::parse('2026-08-20 12:00:00');
        $this->travelTo($now);

        $this->maxManagerWithAiAccess(
            maxUserId: 30_004,
            until: $now->copy()->addMinutes(30),
        );
        config(['phototext.manager_max_user_id' => 30_004]);

        $headers = $this->photoTextHeaders();

        $this->postJson('/api/food/phototext/orders', [], $headers)
            ->assertUnauthorized();

        $this->postJson('/api/food/phototext/schedule/apply', [], $headers)
            ->assertUnauthorized();
    }

    /** Place/apply с неверным write-токеном возвращают 401. */
    public function test_mutations_return_unauthorized_with_wrong_write_token(): void
    {
        $now = Carbon::parse('2026-08-20 12:00:00');
        $this->travelTo($now);

        $this->maxManagerWithAiAccess(
            maxUserId: 30_005,
            until: $now->copy()->addMinutes(30),
        );
        config(['phototext.manager_max_user_id' => 30_005]);

        $headers = $this->photoTextWriteHeaders(writeToken: 'wrong-write-token');

        $this->postJson('/api/food/phototext/orders', [], $headers)
            ->assertUnauthorized();

        $this->postJson('/api/food/phototext/schedule/apply', [], $headers)
            ->assertUnauthorized();
    }

    /** Верные agent + write токены пропускают мутации дальше auth (не 401). */
    public function test_mutations_with_both_tokens_pass_write_auth(): void
    {
        $now = Carbon::parse('2026-08-20 12:00:00');
        $this->travelTo($now);

        $this->maxManagerWithAiAccess(
            maxUserId: 30_006,
            until: $now->copy()->addMinutes(30),
        );
        config(['phototext.manager_max_user_id' => 30_006]);

        $headers = $this->photoTextWriteHeaders();

        $ordersResponse = $this->postJson('/api/food/phototext/orders', [], $headers);
        $this->assertNotSame(Response::HTTP_UNAUTHORIZED, $ordersResponse->status());

        $applyResponse = $this->postJson('/api/food/phototext/schedule/apply', [], $headers);
        $this->assertNotSame(Response::HTTP_UNAUTHORIZED, $applyResponse->status());
    }

    /**
     * Создаёт max_manager с заданным ai_access_until.
     *
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function maxManagerWithAiAccess(int $maxUserId, Carbon $until): array
    {
        $user = MaxUser::query()->create([
            'max_user_id' => $maxUserId,
            'first_name' => 'AiAccessManager'.$maxUserId,
        ]);
        // ai_access_until вне $fillable — только query/forceFill.
        $user->forceFill(['ai_access_until' => $until])->save();

        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser($user),
            FoodOrderAdminRole::MaxManager,
        );

        return $auth;
    }
}
