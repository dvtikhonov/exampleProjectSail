<?php

declare(strict_types=1);

namespace Tests\Feature\MaxIncomingRelay;

use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Enums\MessengerChannel;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;
use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\MessMaxLogTestHelper;
use Tests\Support\ResetsFoodDomainTables;
use Tests\Support\TelegramIncomingRelayPayloadFactory;
use Tests\TestCase;

/**
 * Feature: POST /api/webhooks/telegram message → Home_chat + max_log + persist bot DM.
 */
final class TelegramMessageWebhookTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string SECRET = 'test-telegram-webhook-secret';

    private const string MAX_TOKEN = 'secret-max-token-for-telegram-incoming-relay';

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();

        config([
            'telegram.webhook.secret' => self::SECRET,
            'max.messenger_driver' => 'http',
            'max.bot_access_token' => self::MAX_TOKEN,
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
            'max.ui_stand.recipient_chat_ids' => [-2001],
            'max.ui_stand.recipient_user_ids' => [88888],
            'logging.channels.stack.channels' => ['single'],
        ]);
    }

    /** Private text → 200, текст в Home_chat / max_log, persist для known telegram user. */
    public function test_telegram_message_relays_to_home_chat_and_persists_bot_dm(): void
    {
        $externalUserId = TelegramIncomingRelayPayloadFactory::DEFAULT_TELEGRAM_USER_ID;
        $internalMaxUserId = MessengerChannel::Telegram->toInternalMaxUserId($externalUserId);

        MaxUser::query()->create([
            'max_user_id' => $internalMaxUserId,
            'messenger' => MessengerChannel::Telegram,
            'external_user_id' => $externalUserId,
            'first_name' => 'Demo',
            'last_name' => 'Telegram',
        ]);

        $order = $this->createOrder($internalMaxUserId, '2026-09-20 12:00:00');

        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $payload = TelegramIncomingRelayPayloadFactory::privateTextMessage([
            'date' => 1_790_152_800,
        ]);

        $response = $this->postJson('/api/webhooks/telegram', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ]);

        $response->assertOk();
        $this->assertSame('', $response->getContent());

        $expectedText = implode("\n", [
            'Получено сообщение от Telegram user_id '.$externalUserId.' Demo Telegram',
            'текст сообщения: '.TelegramIncomingRelayPayloadFactory::DEFAULT_TEXT,
            'Дата и время: 23.09.2026 11:40',
            'Дата и номер последнего заказа: 20.09.2026 №'.$order->id,
        ]);

        $relayLog = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX incoming message relay');
        $this->assertSame($expectedText, $relayLog->context['text'] ?? null);
        $this->assertSame($internalMaxUserId, $relayLog->context['user_id'] ?? null);
        $this->assertSame($externalUserId, $relayLog->context['external_user_id'] ?? null);
        $this->assertSame(MessengerChannel::Telegram->value, $relayLog->context['messenger'] ?? null);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($expectedText): bool {
            return str_contains($request->url(), 'chat_id=-2001')
                && ($request['text'] ?? null) === $expectedText;
        });

        $this->assertDatabaseHas('max_bot_direct_messages', [
            'max_user_id' => $internalMaxUserId,
            'sender_max_user_id' => $internalMaxUserId,
            'author_type' => BotDmAuthorType::Customer->value,
            'body' => TelegramIncomingRelayPayloadFactory::DEFAULT_TEXT,
            'chat_id' => $externalUserId,
        ]);
    }

    /** Пользователь вне max_users → Home_chat есть, persist нет. */
    public function test_telegram_message_skips_persist_for_unknown_user(): void
    {
        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $response = $this->postJson(
            '/api/webhooks/telegram',
            TelegramIncomingRelayPayloadFactory::privateTextMessage(['user_id' => 9_990_021]),
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET],
        );

        $response->assertOk();
        MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX incoming message relay');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('max_bot_direct_messages', 0);
    }

    /** Без секрета → 401. */
    public function test_telegram_webhook_rejects_missing_secret(): void
    {
        $this->postJson(
            '/api/webhooks/telegram',
            TelegramIncomingRelayPayloadFactory::privateTextMessage(),
        )->assertUnauthorized();
    }

    private function createOrder(int $maxUserId, string $createdAtMoscow): FoodOrder
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Relay TG Feature', 'Soup', 100);
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
