<?php

declare(strict_types=1);

namespace Tests\Feature\MaxIncomingRelay;

use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\MaxIncomingRelayPayloadFactory;
use Tests\Support\MessMaxLogTestHelper;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Feature: POST /api/webhooks/max message_created → Home_chat + max_log.
 */
final class MessageCreatedWebhookTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string SECRET = 'test-webhook-secret-incoming-relay';

    private const string TOKEN = 'secret-max-token-for-message-created-feature';

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();

        config([
            'max.webhook.secret' => self::SECRET,
            'max.messenger_driver' => 'http',
            'max.bot_access_token' => self::TOKEN,
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
            'max.ui_stand.recipient_chat_ids' => [-2001],
            'max.ui_stand.recipient_user_ids' => [88888],
            'logging.channels.stack.channels' => ['single'],
        ]);
    }

    /** Полный MAX payload message_created → 200, текст в Home_chat и max_log. */
    public function test_message_created_relays_to_home_chat_and_max_log(): void
    {
        $order = $this->createOrder(
            MaxIncomingRelayPayloadFactory::DEFAULT_USER_ID,
            '2026-09-20 12:00:00',
        );

        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $payload = MaxIncomingRelayPayloadFactory::textDialog([
            'timestamp_ms' => 1_790_152_800_000,
        ]);

        $response = $this->postJson('/api/webhooks/max', $payload, [
            'X-Max-Bot-Api-Secret' => self::SECRET,
        ]);

        $response->assertOk();
        $this->assertSame('', $response->getContent());

        $expectedText = implode("\n", [
            'Получено сообщение от user_id 54321 Иван Петров',
            'текст сообщения: Здравствуйте, хочу заказ',
            'Дата и время: 23.09.2026 11:40',
            'Дата и номер последнего заказа: 20.09.2026 №'.$order->id,
        ]);

        $relayLog = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX incoming message relay');
        $this->assertSame($expectedText, $relayLog->context['text'] ?? null);
        $this->assertSame(54321, $relayLog->context['user_id'] ?? null);
        $this->assertSame(MaxIncomingRelayPayloadFactory::DEFAULT_CHAT_ID, $relayLog->context['chat_id'] ?? null);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($expectedText): bool {
            return str_contains($request->url(), 'chat_id=-2001')
                && ($request['text'] ?? null) === $expectedText;
        });
        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), 'user_id=88888');
        });
    }

    /** is_bot: true → 200 без пересылки. */
    public function test_bot_sender_returns_ok_without_relay(): void
    {
        Http::fake();

        $response = $this->postJson(
            '/api/webhooks/max',
            MaxIncomingRelayPayloadFactory::textDialog(['is_bot' => true]),
            ['X-Max-Bot-Api-Secret' => self::SECRET],
        );

        $response->assertOk();
        Http::assertNothingSent();
    }

    /** Битый payload (нет message) → 200 без пересылки. */
    public function test_broken_payload_returns_ok_without_relay(): void
    {
        Http::fake();

        $response = $this->postJson(
            '/api/webhooks/max',
            MaxIncomingRelayPayloadFactory::textDialog(['omit_message' => true]),
            ['X-Max-Bot-Api-Secret' => self::SECRET],
        );

        $response->assertOk();
        Http::assertNothingSent();
    }

    private function createOrder(int $maxUserId, string $createdAtMoscow): FoodOrder
    {
        MaxUser::query()->firstOrCreate(
            ['max_user_id' => $maxUserId],
            ['first_name' => 'RelayFeatureUser'],
        );

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Relay Feature', 'Soup', 100);
        $cart = Cart::query()->create([
            'max_user_id' => $maxUserId,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'ул. Тест, 1',
        ]);

        $order = FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $maxUserId,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::PendingReview,
            'address_review_status' => OrderReviewStatus::Pending,
            'composition_review_status' => OrderReviewStatus::Pending,
            'payment_review_status' => OrderReviewStatus::Pending,
            'total' => '100.00',
            'items_total' => '100.00',
            'items_snapshot' => [],
            'delivery_address' => 'ул. Тест, 1',
        ]);

        $createdAt = new DateTimeImmutable($createdAtMoscow, new DateTimeZone('Europe/Moscow'));
        FoodOrder::query()->whereKey($order->id)->update([
            'created_at' => $createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'updated_at' => $createdAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ]);

        return $order->fresh();
    }
}
