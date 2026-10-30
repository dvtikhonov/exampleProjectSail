<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Delivery\CustomerCategoryName;
use App\Models\Food\CustomerCategory;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\MaxInitDataFixtureBuilder;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

class MaxAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'test-bot-token-for-init-data-validation';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        config([
            'max.bot_access_token' => self::BOT_TOKEN,
            'max.miniapp.auth_date_max_age_seconds' => 86_400,
            'max.miniapp.token_ttl_seconds' => 3_600,
        ]);
    }

    /** Store возвращает Bearer-токен за валидные initData. */
    public function test_store_returns_bearer_token_for_valid_init_data(): void
    {
        $captured = [];

        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
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
            'messenger' => 'max',
            'external_user_id' => 67_890,
            'first_name' => 'Max',
            'customer_category_id' => CustomerCategory::query()
                ->where('name', CustomerCategoryName::Standard->value)
                ->value('id'),
        ]);

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX mini-app auth requested');
        $this->assertSame(200, $log->context['status']);
        $this->assertSame(67_890, $log->context['max_user_id']);
        $this->assertArrayNotHasKey('init_data', $log->context);

        Http::assertSent(function ($request): bool {
            $buttons = $request['attachments'][0]['payload']['buttons'] ?? [];

            return str_contains($request->url(), 'user_id=67890')
                && ($request['text'] ?? null) === 'Привет!. Нужно нажать на кнопку "Заказ еды" или "Открыть".'
                && ($buttons[0][0]['type'] ?? null) === 'open_app'
                && ($buttons[0][0]['text'] ?? null) === 'Заказ еды';
        });

        $this->postJson('/api/max/auth', [
            'init_data' => $initData,
        ])->assertOk();

        Http::assertSentCount(1);

        $greetingLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX registration greeting decision',
        ));
        $this->assertCount(2, $greetingLogs);
        $this->assertTrue($greetingLogs[0]->context['greeting_sent'] ?? false);
        $this->assertFalse($greetingLogs[1]->context['greeting_sent'] ?? true);
        $this->assertTrue($greetingLogs[1]->context['already_sent'] ?? false);
    }

    /** Store возвращает 401 за невалидные initData. */
    public function test_store_returns_unauthorized_for_invalid_init_data(): void
    {
        $captured = [];

        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
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

    /** Store валидирует обязательное поле initData. */
    public function test_store_validates_required_init_data_field(): void
    {
        $response = $this->postJson('/api/max/auth', []);

        $response->assertUnprocessable();
    }

    /** Защищённый маршрут принимает выданный токен. */
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

    /** Повторно выдаёт токен и обновляет существующего пользователя MAX. */
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
            'messenger' => 'max',
            'external_user_id' => 67_890,
            'first_name' => 'Max',
            'customer_category_id' => CustomerCategory::query()
                ->where('name', CustomerCategoryName::Standard->value)
                ->value('id'),
        ]);

        $this->assertSame(1, MaxUser::query()->whereKey(67_890)->first()?->tokens()->count());

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'user_id=67890')
                && ($request['text'] ?? null) === 'Привет!. Нужно нажать на кнопку "Заказ еды" или "Открыть".';
        });
    }

    /** Повторная аутентификация не перезаписывает существующую категорию клиента. */
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
            'messenger' => 'max',
            'external_user_id' => 67_890,
            'first_name' => 'Max',
            'customer_category_id' => $vipCategory->id,
        ]);
    }

    /** Store возвращает 429 при превышении rate limit (20/мин). */
    public function test_store_returns_too_many_requests_when_rate_limited(): void
    {
        $payload = ['init_data' => 'auth_date=1&user=%7B%7D&hash=deadbeef'];

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/max/auth', $payload)->assertUnauthorized();
        }

        $this->postJson('/api/max/auth', $payload)->assertTooManyRequests();
    }
}
