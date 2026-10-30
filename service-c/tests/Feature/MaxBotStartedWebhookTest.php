<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Delivery\CustomerCategoryName;
use App\Enums\MessengerChannel;
use App\Models\Food\CustomerCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

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
}
