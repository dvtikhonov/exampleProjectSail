<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class MaxAiAccessApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetFoodDomainTables();
    }

    /** Без роли max_manager доступ к API ai-access запрещён. */
    public function test_ai_access_endpoints_forbidden_without_max_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/ai-access', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ запрещён.');

        $this->postJson('/api/food/admin/ai-access/toggle', [], $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ запрещён.');
    }

    /** Включение AI-доступа создаёт TTL 30 минут и повторный клик выключает доступ. */
    public function test_toggle_enables_and_disables_ai_access_for_same_manager(): void
    {
        $now = Carbon::parse('2026-08-19 10:00:00');
        $this->travelTo($now);

        $manager = $this->maxManagerAuth(20_001);

        $enableResponse = $this->postJson('/api/food/admin/ai-access/toggle', [], $manager['headers']);
        $enableResponse
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('active_max_user_id', $manager['user']->max_user_id);

        $expiresAt = Carbon::parse((string) $enableResponse->json('expires_at'));
        $this->assertTrue($expiresAt->equalTo($now->copy()->addMinutes(30)));

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $manager['user']->max_user_id,
            'ai_access_until' => $now->copy()->addMinutes(30)->toDateTimeString(),
        ]);

        $this->postJson('/api/food/admin/ai-access/toggle', [], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('active_max_user_id', null)
            ->assertJsonPath('expires_at', null);

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $manager['user']->max_user_id,
            'ai_access_until' => null,
        ]);
    }

    /** Второй менеджер не может включить AI-доступ, пока он активен у первого. */
    public function test_toggle_returns_conflict_when_other_manager_has_active_access(): void
    {
        $now = Carbon::parse('2026-08-19 11:00:00');
        $this->travelTo($now);

        $firstManager = $this->maxManagerAuth(20_011);
        $secondManager = $this->maxManagerAuth(20_012);

        $this->postJson('/api/food/admin/ai-access/toggle', [], $firstManager['headers'])
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('active_max_user_id', $firstManager['user']->max_user_id);

        $this->postJson('/api/food/admin/ai-access/toggle', [], $secondManager['headers'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'уже разрешен доступ AI к базе');

        $this->getJson('/api/food/admin/ai-access', $firstManager['headers'])
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('active_max_user_id', $firstManager['user']->max_user_id);
    }

    /** GET ai-access очищает просроченный ai_access_until и возвращает выключенный статус. */
    public function test_show_clears_expired_ai_access_and_returns_disabled_status(): void
    {
        $now = Carbon::parse('2026-08-19 12:30:00');
        $this->travelTo($now);

        $manager = $this->maxManagerAuth(20_021);
        $manager['user']->forceFill([
            'ai_access_until' => $now->copy()->subMinute(),
        ])->save();

        $this->getJson('/api/food/admin/ai-access', $manager['headers'])
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('active_max_user_id', null)
            ->assertJsonPath('expires_at', null);

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $manager['user']->max_user_id,
            'ai_access_until' => null,
        ]);
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function maxManagerAuth(int $maxUserId): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'MaxManager'.$maxUserId,
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }
}
