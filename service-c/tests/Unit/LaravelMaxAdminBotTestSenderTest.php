<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\Food\FoodDomainException;
use App\Services\Max\LaravelMaxAdminBotTestSender;
use App\Support\MaxUiStandRecipientRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LaravelMaxAdminBotTestSenderTest extends TestCase
{
    private const TOKEN = 'secret-max-token-for-admin-bot-test';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'max.bot_access_token' => self::TOKEN,
            'max.bot_username' => 'food_test_bot',
            'max.order_notifications.chat_ids' => [111],
            'max.order_notifications.user_ids' => [222],
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
        ]);
    }

    /** Тестовое сообщение уходит получателям уведомлений о заказах. */
    public function test_send_test_message_posts_to_order_notification_recipients(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $result = $this->app->make(LaravelMaxAdminBotTestSender::class)->sendTestMessage();

        $this->assertSame(2, $result->sentCount);

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://platform-api.max.ru/messages?chat_id=111'
                && $request['text'] === LaravelMaxAdminBotTestSender::TEST_MESSAGE_TEXT;
        });
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://platform-api.max.ru/messages?user_id=222'
                && $request['text'] === LaravelMaxAdminBotTestSender::TEST_MESSAGE_TEXT;
        });
    }

    /** Тестовое сообщение падает, если получатели отсутствуют. */
    public function test_send_test_message_fails_when_recipients_are_missing(): void
    {
        config([
            'max.order_notifications.chat_ids' => [],
            'max.order_notifications.user_ids' => [],
        ]);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('MAX_REPORT_CHAT_IDS');

        $this->app->make(LaravelMaxAdminBotTestSender::class)->sendTestMessage();
    }

    /** Тестовое сообщение падает, если username бота отсутствует. */
    public function test_send_test_message_fails_when_bot_username_is_missing(): void
    {
        config(['max.bot_username' => '']);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('MAX_BOT_USERNAME');

        $this->app->make(LaravelMaxAdminBotTestSender::class)->sendTestMessage();
    }

    /** Тестовое сообщение падает, если access token бота отсутствует. */
    public function test_send_test_message_fails_when_bot_access_token_is_missing(): void
    {
        config(['max.bot_access_token' => '']);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('MAX_BOT_ACCESS_TOKEN');

        $this->app->make(LaravelMaxAdminBotTestSender::class)->sendTestMessage();
    }

    /** Тест UI-стенда шлёт тем же получателям, что и приветствие. */
    public function test_send_ui_stand_test_message_posts_to_same_recipients_as_ui_stand_greeting(): void
    {
        config([
            'max.ui_stand.recipient_chat_ids' => [333],
            'max.ui_stand.recipient_user_ids' => [444],
        ]);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $result = $this->app->make(LaravelMaxAdminBotTestSender::class)->sendUiStandTestMessage();

        $this->assertSame(2, $result->sentCount);

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://platform-api.max.ru/messages?chat_id=333'
                && $request['text'] === LaravelMaxAdminBotTestSender::UI_STAND_TEST_MESSAGE_TEXT;
        });
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://platform-api.max.ru/messages?user_id=444'
                && $request['text'] === LaravelMaxAdminBotTestSender::UI_STAND_TEST_MESSAGE_TEXT;
        });
    }

    /** Тест UI-стенда использует chat_id, зарегистрированный из callback. */
    public function test_send_ui_stand_test_message_uses_registered_chat_from_callback(): void
    {
        config([
            'max.ui_stand.recipient_chat_ids' => [],
            'max.ui_stand.recipient_user_ids' => [],
        ]);

        $this->app->make(MaxUiStandRecipientRegistry::class)->rememberChatId(-100500);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $result = $this->app->make(LaravelMaxAdminBotTestSender::class)->sendUiStandTestMessage();

        $this->assertSame(1, $result->sentCount);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://platform-api.max.ru/messages?chat_id=-100500'
                && $request['text'] === LaravelMaxAdminBotTestSender::UI_STAND_TEST_MESSAGE_TEXT;
        });
    }

    /** Тест UI-стенда падает, если получатели стенда отсутствуют. */
    public function test_send_ui_stand_test_message_fails_when_ui_stand_recipients_are_missing(): void
    {
        config([
            'max.ui_stand.recipient_chat_ids' => [],
            'max.ui_stand.recipient_user_ids' => [],
        ]);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('MAX_UI_STAND_CHAT_IDS');

        $this->app->make(LaravelMaxAdminBotTestSender::class)->sendUiStandTestMessage();
    }
}
