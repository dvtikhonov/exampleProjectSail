<?php

declare(strict_types=1);

namespace Tests\Feature\MaxIncomingRelay;

use App\Contracts\Telegram\TelegramMessengerClientInterface;
use App\DTO\Telegram\TelegramInlineKeyboardButtonDto;
use App\Enums\Food\Delivery\CustomerCategoryName;
use App\Enums\MessengerChannel;
use App\Models\Food\CustomerCategory;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\MessMaxLogTestHelper;
use Tests\Support\ResetsFoodDomainTables;
use Tests\Support\TelegramIncomingRelayPayloadFactory;
use Tests\TestCase;

/**
 * Feature: POST /api/webhooks/telegram /start → регистрация + приветствие с Mini App.
 */
final class TelegramStartWebhookTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string SECRET = 'test-telegram-webhook-secret';

    private const string GREETING = 'Привет!. Нужно нажать на кнопку "Заказ еды" или "Открыть".';

    private const string MINI_APP_URL = 'https://example.test/tg-app';

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();

        config([
            'telegram.webhook.secret' => self::SECRET,
            'telegram.mini_app_url' => self::MINI_APP_URL,
            'telegram.ui_stand.greeting_text' => self::GREETING,
            'telegram.ui_stand.mini_app_button_text' => 'Заказ еды',
            'max.messenger_driver' => 'http',
            'max.bot_access_token' => 'secret-max-token-for-telegram-start',
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
            'max.ui_stand.recipient_chat_ids' => [-2001],
            'logging.channels.stack.channels' => ['single'],
        ]);
    }

    /** /start для нового пользователя → max_users + greeting, без релей в Home_chat. */
    public function test_start_registers_unknown_user_and_sends_greeting(): void
    {
        $externalUserId = 999_166_528;
        $internalMaxUserId = MessengerChannel::Telegram->toInternalMaxUserId($externalUserId);

        $sent = [];
        $telegramClient = $this->createMock(TelegramMessengerClientInterface::class);
        $telegramClient->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function (int $chatId, string $text, array $buttonRows) use (&$sent): void {
                $sent = compact('chatId', 'text', 'buttonRows');
            });
        $this->app->instance(TelegramMessengerClientInterface::class, $telegramClient);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $response = $this->postJson(
            '/api/webhooks/telegram',
            TelegramIncomingRelayPayloadFactory::privateTextMessage([
                'user_id' => $externalUserId,
                'first_name' => 'Дмитрий',
                'last_name' => 'Тихонов',
                'username' => 'dmitry_t',
                'language_code' => 'ru',
                'text' => '/start',
            ]),
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET],
        );

        $response->assertOk();
        $this->assertSame('', $response->getContent());

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $internalMaxUserId,
            'messenger' => MessengerChannel::Telegram->value,
            'external_user_id' => $externalUserId,
            'first_name' => 'Дмитрий',
            'last_name' => 'Тихонов',
            'username' => 'dmitry_t',
            'language_code' => 'ru',
            'customer_category_id' => CustomerCategory::query()
                ->where('name', CustomerCategoryName::Standard->value)
                ->value('id'),
        ]);

        $this->assertSame($externalUserId, $sent['chatId'] ?? null);
        $this->assertSame(self::GREETING, $sent['text'] ?? null);
        $this->assertInstanceOf(TelegramInlineKeyboardButtonDto::class, $sent['buttonRows'][0][0] ?? null);
        $this->assertSame('Заказ еды', $sent['buttonRows'][0][0]->text);
        $this->assertSame(self::MINI_APP_URL, $sent['buttonRows'][0][0]->webAppUrl);

        Http::assertNothingSent();
        $this->assertDatabaseCount('max_bot_direct_messages', 0);

        MessMaxLogTestHelper::assertSingleMessage($captured, 'telegram_bot_started');
    }

    /** Повторный /start не создаёт дубликат и снова шлёт экран. */
    public function test_start_for_existing_user_updates_profile_and_sends_greeting(): void
    {
        $externalUserId = 777_001;
        $internalMaxUserId = MessengerChannel::Telegram->toInternalMaxUserId($externalUserId);

        MaxUser::query()->create([
            'max_user_id' => $internalMaxUserId,
            'messenger' => MessengerChannel::Telegram,
            'external_user_id' => $externalUserId,
            'first_name' => 'Old',
            'last_name' => 'Name',
        ]);

        $telegramClient = $this->createMock(TelegramMessengerClientInterface::class);
        $telegramClient->expects($this->once())->method('sendMessage');
        $this->app->instance(TelegramMessengerClientInterface::class, $telegramClient);

        Http::fake();

        $this->postJson(
            '/api/webhooks/telegram',
            TelegramIncomingRelayPayloadFactory::privateTextMessage([
                'user_id' => $externalUserId,
                'first_name' => 'Demo',
                'last_name' => 'Telegram',
                'text' => '/start@obedov_bot',
            ]),
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET],
        )->assertOk();

        $this->assertDatabaseCount('max_users', 1);
        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $internalMaxUserId,
            'first_name' => 'Demo',
            'last_name' => 'Telegram',
        ]);
        Http::assertNothingSent();
    }

    /** /start с payload тоже обрабатывается как старт, не как обычный DM. */
    public function test_start_with_payload_does_not_relay_to_home_chat(): void
    {
        $telegramClient = $this->createMock(TelegramMessengerClientInterface::class);
        $telegramClient->expects($this->once())->method('sendMessage');
        $this->app->instance(TelegramMessengerClientInterface::class, $telegramClient);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $this->postJson(
            '/api/webhooks/telegram',
            TelegramIncomingRelayPayloadFactory::privateTextMessage([
                'user_id' => 9_990_100,
                'text' => '/start ref123',
            ]),
            ['X-Telegram-Bot-Api-Secret-Token' => self::SECRET],
        )->assertOk();

        Http::assertNothingSent();
        $this->assertDatabaseCount('max_bot_direct_messages', 0);
    }
}
