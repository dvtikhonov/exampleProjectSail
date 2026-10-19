<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Delivery\CustomerCategoryName;
use App\Enums\MessengerChannel;
use App\Models\Food\CustomerCategory;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\TelegramInitDataFixtureBuilder;
use Tests\TestCase;

class TelegramAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'telegram.bot_token' => self::BOT_TOKEN,
            'telegram.miniapp.auth_date_max_age_seconds' => 86_400,
            'telegram.miniapp.token_ttl_seconds' => 3_600,
        ]);
    }

    /** Store возвращает Bearer-токен за валидные initData. */
    public function test_store_returns_bearer_token_for_valid_init_data(): void
    {
        $initData = TelegramInitDataFixtureBuilder::build(self::BOT_TOKEN);
        $expectedInternalId = MessengerChannel::Telegram->toInternalMaxUserId(67_890);

        $response = $this->postJson('/api/telegram/auth', [
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
            ->assertJsonPath('user.max_user_id', $expectedInternalId)
            ->assertJsonPath('user.first_name', 'Telegram');

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $expectedInternalId,
            'messenger' => 'telegram',
            'external_user_id' => 67_890,
            'first_name' => 'Telegram',
            'customer_category_id' => CustomerCategory::query()
                ->where('name', CustomerCategoryName::Standard->value)
                ->value('id'),
        ]);

        $token = PersonalAccessToken::findToken((string) $response->json('token'));
        $this->assertNotNull($token);
        $this->assertTrue($token->can('telegram-miniapp'));
        $this->assertFalse($token->can('max-miniapp'));
    }

    /** Store возвращает 401 за невалидные initData. */
    public function test_store_returns_unauthorized_for_invalid_init_data(): void
    {
        $response = $this->postJson('/api/telegram/auth', [
            'init_data' => 'auth_date=1&user=%7B%7D&hash=deadbeef',
        ]);

        $response->assertUnauthorized();
        $message = (string) $response->json('message');
        $this->assertStringStartsWith('Invalid Telegram initData.', $message);
        $this->assertNotEmpty($response->json('debug_reason'));
    }

    /** Store валидирует обязательное поле init_data. */
    public function test_store_validates_required_init_data_field(): void
    {
        $this->postJson('/api/telegram/auth', [])->assertUnprocessable();
    }

    /** Защищённый клиентский маршрут принимает токен telegram-miniapp. */
    public function test_protected_client_route_accepts_telegram_miniapp_token(): void
    {
        $initData = TelegramInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $authResponse = $this->postJson('/api/telegram/auth', [
            'init_data' => $initData,
        ]);

        $token = (string) $authResponse->json('token');
        $expectedInternalId = MessengerChannel::Telegram->toInternalMaxUserId(67_890);

        $meResponse = $this->getJson('/api/max/me', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $meResponse
            ->assertOk()
            ->assertJsonPath('max_user_id', $expectedInternalId);
    }

    /** Одинаковый external id у MAX и Telegram — две строки после auth. */
    public function test_same_external_id_as_max_creates_separate_telegram_row(): void
    {
        MaxUser::query()->create([
            'max_user_id' => 67_890,
            'messenger' => MessengerChannel::Max,
            'external_user_id' => 67_890,
            'first_name' => 'MaxTwin',
        ]);

        $initData = TelegramInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $this->postJson('/api/telegram/auth', [
            'init_data' => $initData,
        ])->assertOk();

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => 67_890,
            'messenger' => 'max',
            'external_user_id' => 67_890,
            'first_name' => 'MaxTwin',
        ]);
        $this->assertDatabaseHas('max_users', [
            'max_user_id' => MessengerChannel::Telegram->toInternalMaxUserId(67_890),
            'messenger' => 'telegram',
            'external_user_id' => 67_890,
            'first_name' => 'Telegram',
        ]);
    }

    /** Токен telegram-miniapp на /api/food/admin/* → 403. */
    public function test_telegram_miniapp_token_is_forbidden_on_food_admin(): void
    {
        $initData = TelegramInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $token = (string) $this->postJson('/api/telegram/auth', [
            'init_data' => $initData,
        ])->json('token');

        $this->getJson('/api/food/admin/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Telegram Mini App token cannot access admin endpoints.');
    }

    /** Токен telegram-miniapp на SchedulePhoto admin → 403. */
    public function test_telegram_miniapp_token_is_forbidden_on_schedule_photo_admin(): void
    {
        $initData = TelegramInitDataFixtureBuilder::build(self::BOT_TOKEN);

        $token = (string) $this->postJson('/api/telegram/auth', [
            'init_data' => $initData,
        ])->json('token');

        $this->getJson('/api/food/admin/schedule-photo/config', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Telegram Mini App token cannot access admin endpoints.');
    }

    /** Store возвращает 429 при превышении rate limit (20/мин). */
    public function test_store_returns_too_many_requests_when_rate_limited(): void
    {
        $payload = ['init_data' => 'auth_date=1&user=%7B%7D&hash=deadbeef'];

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/telegram/auth', $payload)->assertUnauthorized();
        }

        $this->postJson('/api/telegram/auth', $payload)->assertTooManyRequests();
    }
}
