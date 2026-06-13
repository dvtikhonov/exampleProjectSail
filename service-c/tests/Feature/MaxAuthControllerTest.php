<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\CustomerCategoryName;
use App\Models\CustomerCategory;
use App\Models\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\Support\MaxInitDataFixtureBuilder;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

class MaxAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'test-bot-token-for-init-data-validation';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'max.bot_access_token' => self::BOT_TOKEN,
            'max.miniapp.auth_date_max_age_seconds' => 86_400,
            'max.miniapp.token_ttl_seconds' => 3_600,
        ]);
    }

    public function test_store_returns_bearer_token_for_valid_init_data(): void
    {
        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $initData = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $response = $this->postJson('/api/max/auth', [
            'init_data' => $initData,
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'expires_in',
                'user' => [
                    'max_user_id',
                    'first_name',
                ],
            ])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 3_600)
            ->assertJsonPath('user.max_user_id', 67_890)
            ->assertJsonPath('user.first_name', 'Max');

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => 67_890,
            'first_name' => 'Max',
            'customer_category_id' => CustomerCategory::query()
                ->where('name', CustomerCategoryName::Standard->value)
                ->value('id'),
        ]);

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX mini-app auth requested');
        $this->assertSame(200, $log->context['status']);
        $this->assertSame(67_890, $log->context['max_user_id']);
        $this->assertArrayNotHasKey('init_data', $log->context);
    }

    public function test_store_returns_unauthorized_for_invalid_init_data(): void
    {
        $captured = [];

        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $response = $this->postJson('/api/max/auth', [
            'init_data' => 'auth_date=1&user=%7B%7D&hash=deadbeef',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid MAX initData.');

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX mini-app auth requested');
        $this->assertSame(401, $log->context['status']);
        $this->assertNull($log->context['max_user_id']);
    }

    public function test_store_validates_required_init_data_field(): void
    {
        $response = $this->postJson('/api/max/auth', []);

        $response->assertUnprocessable();
    }

    public function test_protected_route_accepts_issued_token(): void
    {
        $initData = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $authResponse = $this->postJson('/api/max/auth', [
            'init_data' => $initData,
        ]);

        $token = (string) $authResponse->json('token');

        $meResponse = $this->getJson('/api/max/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $meResponse
            ->assertOk()
            ->assertJsonPath('max_user_id', 67_890);
    }

    public function test_re_issues_token_and_updates_existing_max_user(): void
    {
        MaxUser::query()->create([
            'max_user_id' => 67_890,
            'first_name' => 'OldName',
        ]);

        $initData = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $response = $this->postJson('/api/max/auth', [
            'init_data' => $initData,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => 67_890,
            'first_name' => 'Max',
            'customer_category_id' => null,
        ]);

        $this->assertSame(1, MaxUser::query()->whereKey(67_890)->first()?->tokens()->count());
    }

    public function test_re_auth_does_not_overwrite_existing_customer_category(): void
    {
        $vipCategory = CustomerCategory::query()->create([
            'name' => 'VIP',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        MaxUser::query()->create([
            'max_user_id' => 67_890,
            'first_name' => 'OldName',
            'customer_category_id' => $vipCategory->id,
        ]);

        $initData = MaxInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $this->postJson('/api/max/auth', [
            'init_data' => $initData,
        ])->assertOk();

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => 67_890,
            'first_name' => 'Max',
            'customer_category_id' => $vipCategory->id,
        ]);
    }
}
