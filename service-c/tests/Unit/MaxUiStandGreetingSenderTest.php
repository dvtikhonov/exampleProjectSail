<?php

namespace Tests\Unit;

use App\Services\Max\UiStand\MaxUiStandGreetingSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MaxUiStandGreetingSenderTest extends TestCase
{
    private const TOKEN = 'secret-max-token-for-ui-stand-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'max.bot_access_token' => self::TOKEN,
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
            'max.ui_stand.mini_app_url' => 'https://example.test/max-app',
            'max.ui_stand.mini_app_button_text' => 'Заказ еды',
            'max.ui_stand.greeting_text' => 'Привет! Выберите ответ:',
            'max.ui_stand.button_yes_payload' => 'yes',
            'max.ui_stand.button_no_payload' => 'no',
            'max.ui_stand.recipient_chat_ids' => [111],
            'max.ui_stand.recipient_user_ids' => [222],
        ]);
    }

    public function test_send_posts_inline_keyboard_payload_for_each_recipient(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $this->app->make(MaxUiStandGreetingSender::class)->send();

        Http::assertSentCount(2);

        Http::assertSent(function ($request): bool {
            $attachments = $request['attachments'] ?? [];
            $buttons = $attachments[0]['payload']['buttons'] ?? [];

            return str_contains($request->url(), 'chat_id=111')
                && $request->hasHeader('Authorization', self::TOKEN)
                && $request['text'] === 'Привет! Выберите ответ:'
                && ($attachments[0]['type'] ?? null) === 'inline_keyboard'
                && ($buttons[0][0]['type'] ?? null) === 'open_app'
                && ($buttons[0][0]['text'] ?? null) === 'Заказ еды'
                && ($buttons[0][0]['web_app'] ?? null) === 'https://example.test/max-app'
                && ($buttons[1][0]['type'] ?? null) === 'callback'
                && ($buttons[1][0]['text'] ?? null) === 'да'
                && ($buttons[1][0]['payload'] ?? null) === 'yes'
                && ($buttons[1][1]['text'] ?? null) === 'нет'
                && ($buttons[1][1]['payload'] ?? null) === 'no';
        });

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'user_id=222')
                && $request->hasHeader('Authorization', self::TOKEN)
                && ($request['attachments'][0]['type'] ?? null) === 'inline_keyboard';
        });
    }

    public function test_send_throws_when_no_recipients_configured(): void
    {
        config([
            'max.ui_stand.recipient_chat_ids' => [],
            'max.ui_stand.recipient_user_ids' => [],
        ]);

        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MAX UI stand recipients are not configured.');

        $this->app->make(MaxUiStandGreetingSender::class)->send();

        Http::assertNothingSent();
    }

    public function test_send_continues_when_one_recipient_fails(): void
    {
        Http::fake([
            'platform-api.max.ru/messages?chat_id=111' => Http::response(['message' => ['id' => 1]], 200),
            'platform-api.max.ru/messages?user_id=222' => Http::response(['code' => 'not.found'], 404),
        ]);

        $this->app->make(MaxUiStandGreetingSender::class)->send();

        Http::assertSentCount(2);
    }

    public function test_send_prefers_mini_app_url_over_bot_username(): void
    {
        config([
            'max.bot_username' => 'my_food_bot',
            'max.ui_stand.mini_app_url' => 'https://example.test/max-app',
        ]);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $this->app->make(MaxUiStandGreetingSender::class)->send();

        Http::assertSent(function ($request): bool {
            $buttons = $request['attachments'][0]['payload']['buttons'] ?? [];

            return ($buttons[0][0]['web_app'] ?? null) === 'https://example.test/max-app';
        });
    }

    public function test_send_uses_webhook_origin_when_mini_app_url_is_not_set(): void
    {
        config([
            'max.bot_username' => 'my_food_bot',
            'max.ui_stand.mini_app_url' => '',
            'max.webhook.url' => 'https://tunnel.example.test/api/webhooks/max',
        ]);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $this->app->make(MaxUiStandGreetingSender::class)->send();

        Http::assertSent(function ($request): bool {
            $buttons = $request['attachments'][0]['payload']['buttons'] ?? [];

            return ($buttons[0][0]['web_app'] ?? null) === 'https://tunnel.example.test/max-app';
        });
    }

    public function test_send_uses_max_ru_link_when_only_bot_username_is_set(): void
    {
        config([
            'max.bot_username' => 'my_food_bot',
            'max.ui_stand.mini_app_url' => '',
            'max.webhook.url' => '',
            'max.bot_user_id' => 421816864057,
        ]);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $this->app->make(MaxUiStandGreetingSender::class)->send();

        Http::assertSent(function ($request): bool {
            $buttons = $request['attachments'][0]['payload']['buttons'] ?? [];

            return ($buttons[0][0]['web_app'] ?? null) === 'https://max.ru/my_food_bot'
                && ($buttons[0][0]['contact_id'] ?? null) === 421816864057;
        });
    }

    public function test_send_to_user_posts_only_for_given_user_id(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $this->app->make(MaxUiStandGreetingSender::class)->sendToUser(333);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'user_id=333');
        });
    }
}
