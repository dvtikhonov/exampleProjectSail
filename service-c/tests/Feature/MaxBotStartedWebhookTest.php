<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Shared\CacheStoreInterface;
use App\Enums\Food\Delivery\CustomerCategoryName;
use App\Enums\MessengerChannel;
use App\Models\Food\CustomerCategory;
use App\Services\Max\UiStand\BotStartedUpdateHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Feature: POST /api/webhooks/max bot_started → строка в max_users и приветствие.
 */
final class MaxBotStartedWebhookTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();

        config([
            'max.webhook.secret' => self::SECRET,
            'max.bot_access_token' => 'secret-max-token-for-bot-started',
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
            'max.ui_stand.greeting_text' => 'Привет!. Нужно нажать на кнопку "Заказ еды" или "Открыть".',
            'logging.channels.stack.channels' => ['single'],
        ]);
    }

    /** bot_started с профилем создаёт одну строку max_users и отвечает 200. */
    public function test_bot_started_upserts_max_user_from_profile(): void
    {
        $claimedDuringSend = false;
        Http::fake(function () use (&$claimedDuringSend) {
            $claimedDuringSend = Cache::get('max.registration_greeting.chat.1234567890') === true;

            return Http::response(['message' => ['id' => 1]], 200);
        });

        $payload = [
            'update_type' => 'bot_started',
            'timestamp' => 1_579_226_679_188,
            'chat_id' => 1_234_567_890,
            'user' => [
                'user_id' => 910_127_594,
                'first_name' => 'Иван',
                'last_name' => 'Петров',
                'name' => 'Иван Петров',
            ],
        ];

        $this->postJson('/api/webhooks/max', $payload, [
            'X-Max-Bot-Api-Secret' => self::SECRET,
        ])->assertOk();

        $this->postJson('/api/webhooks/max', $payload, [
            'X-Max-Bot-Api-Secret' => self::SECRET,
        ])->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'chat_id=1234567890')
                && ! str_contains($request->url(), 'user_id=');
        });
        $this->assertTrue($claimedDuringSend);

        $this->assertDatabaseCount('max_users', 1);
        $this->assertDatabaseHas('max_users', [
            'max_user_id' => 910_127_594,
            'messenger' => MessengerChannel::Max->value,
            'external_user_id' => 910_127_594,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'username' => null,
            'customer_category_id' => CustomerCategory::query()
                ->where('name', CustomerCategoryName::Standard->value)
                ->value('id'),
        ]);
    }

    /** Бессрочные ключи user_id не блокируют приветствие в chat_id диалога. */
    public function test_bot_started_sends_greeting_when_legacy_cache_flag_is_set(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        Cache::forever('max.registration_greeting.sent.910127594', true);
        Cache::forever('max.registration_greeting.sent.v2.910127594', true);

        $this->postJson('/api/webhooks/max', [
            'update_type' => 'bot_started',
            'timestamp' => 1_579_226_679_188,
            'chat_id' => 1_234_567_890,
            'user' => [
                'user_id' => 910_127_594,
                'first_name' => 'Иван',
            ],
        ], [
            'X-Max-Bot-Api-Secret' => self::SECRET,
        ])->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'chat_id=1234567890');
        });
        $this->assertTrue(Cache::get('max.registration_greeting.sent.v2.910127594') === true);
    }

    /** bot_started без валидного user_id → 200, без upsert/greeting; warning + счётчик. */
    public function test_bot_started_with_invalid_user_id_is_noop_with_warning_and_counter(): void
    {
        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake();
        Cache::forget(BotStartedUpdateHandler::INVALID_PAYLOAD_COUNTER_KEY);

        $this->postJson('/api/webhooks/max', [
            'update_type' => 'bot_started',
            'timestamp' => 1_579_226_679_188,
            'chat_id' => 1_234_567_890,
            'user' => [
                'user_id' => 0,
                'first_name' => 'Broken',
            ],
        ], [
            'X-Max-Bot-Api-Secret' => self::SECRET,
        ])->assertOk();

        Http::assertNothingSent();
        $this->assertDatabaseCount('max_users', 0);

        $warnings = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'bot_started invalid payload',
        ));
        $this->assertCount(1, $warnings);
        $this->assertSame('warning', $warnings[0]->level);
        $this->assertSame(0, $warnings[0]->context['user_id'] ?? null);
        $this->assertSame(1_234_567_890, $warnings[0]->context['chat_id'] ?? null);
        $this->assertTrue($warnings[0]->context['has_user'] ?? false);

        $this->assertSame(
            1,
            $this->app->make(CacheStoreInterface::class)->get(
                BotStartedUpdateHandler::INVALID_PAYLOAD_COUNTER_KEY,
            ),
        );
    }

    /** bot_started без объекта user → 200, warning + счётчик, без side-effects. */
    public function test_bot_started_without_user_object_is_noop_with_warning_and_counter(): void
    {
        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake();
        Cache::forget(BotStartedUpdateHandler::INVALID_PAYLOAD_COUNTER_KEY);

        $this->postJson('/api/webhooks/max', [
            'update_type' => 'bot_started',
            'timestamp' => 1_579_226_679_188,
        ], [
            'X-Max-Bot-Api-Secret' => self::SECRET,
        ])->assertOk();

        Http::assertNothingSent();
        $this->assertDatabaseCount('max_users', 0);

        $warnings = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'bot_started invalid payload',
        ));
        $this->assertCount(1, $warnings);
        $this->assertFalse($warnings[0]->context['has_user'] ?? true);

        $this->assertSame(
            1,
            $this->app->make(CacheStoreInterface::class)->get(
                BotStartedUpdateHandler::INVALID_PAYLOAD_COUNTER_KEY,
            ),
        );
    }
}
