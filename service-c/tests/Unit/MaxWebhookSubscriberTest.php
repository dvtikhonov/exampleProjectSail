<?php

namespace Tests\Unit;

use App\Services\Max\UiStand\MaxWebhookSubscriber;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Shared\MaxMessenger\Exceptions\MaxMessengerAuthException;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;
use Tests\TestCase;

class MaxWebhookSubscriberTest extends TestCase
{
    private const TOKEN = 'secret-max-token-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'max.bot_access_token' => self::TOKEN,
            'max.webhook.url' => 'https://example.ngrok.io/api/webhooks/max',
            'max.webhook.secret' => 'stand-secret',
        ]);
    }

    protected function tearDown(): void
    {
        Http::fake();

        parent::tearDown();
    }

    public function test_subscribe_posts_subscription_payload_to_max_api(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['success' => true], 200),
        ]);

        $this->app->make(MaxWebhookSubscriber::class)->subscribe();

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://platform-api.max.ru/subscriptions'
                && $request->hasHeader('Authorization', self::TOKEN)
                && $request['url'] === 'https://example.ngrok.io/api/webhooks/max'
                && $request['secret'] === 'stand-secret'
                && $request['update_types'] === ['message_callback', 'bot_started'];
        });
    }

    public function test_subscribe_fails_when_webhook_url_is_missing(): void
    {
        config(['max.webhook.url' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MAX_WEBHOOK_URL не задан в конфигурации.');

        $this->app->make(MaxWebhookSubscriber::class)->subscribe();
    }

    public function test_subscribe_fails_when_webhook_secret_is_too_short(): void
    {
        config(['max.webhook.secret' => 'abc']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MAX_WEBHOOK_SECRET должен содержать минимум 5 символов.');

        $this->app->make(MaxWebhookSubscriber::class)->subscribe();
    }

    public function test_subscribe_fails_when_webhook_url_is_not_https(): void
    {
        config(['max.webhook.url' => 'http://example.ngrok.io/api/webhooks/max']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MAX_WEBHOOK_URL должен начинаться с https://');

        $this->app->make(MaxWebhookSubscriber::class)->subscribe();
    }

    public function test_subscribe_fails_when_webhook_secret_has_invalid_characters(): void
    {
        config(['max.webhook.secret' => 'dev secret!']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MAX_WEBHOOK_SECRET может содержать только');

        $this->app->make(MaxWebhookSubscriber::class)->subscribe();
    }

    public function test_subscribe_throws_auth_exception_on_401(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(MaxMessengerAuthException::class);

        $this->app->make(MaxWebhookSubscriber::class)->subscribe();
    }

    public function test_subscribe_throws_request_exception_on_400(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => 'Bad Request'], 400),
        ]);

        try {
            $this->app->make(MaxWebhookSubscriber::class)->subscribe();
            $this->fail('Expected MaxMessengerRequestException was not thrown.');
        } catch (MaxMessengerRequestException $exception) {
            $this->assertStringContainsString('MAX_WEBHOOK_URL', $exception->getMessage());
        }
    }

    public function test_unsubscribe_stale_dev_tunnels_removes_only_trycloudflare_urls(): void
    {
        $configuredUrl = 'https://fresh-id.trycloudflare.com/api/webhooks/max';
        $salebotUrl = 'https://chatter.salebot.pro/tamtam_webhook/example';
        $staleTunnelUrl = 'https://old-id.trycloudflare.com/api/webhooks/max';

        config(['max.webhook.url' => $configuredUrl]);

        Http::fake(function ($request) use ($configuredUrl, $salebotUrl, $staleTunnelUrl) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/subscriptions')) {
                return Http::response([
                    'subscriptions' => [
                        ['url' => $salebotUrl],
                        ['url' => $staleTunnelUrl],
                        ['url' => $configuredUrl],
                    ],
                ], 200);
            }

            if ($request->method() === 'DELETE' && str_contains($request->url(), '/subscriptions')) {
                return Http::response(['success' => true], 200);
            }

            return Http::response([], 404);
        });

        $result = $this->app->make(MaxWebhookSubscriber::class)
            ->unsubscribeStaleDevTunnels($configuredUrl);

        $this->assertSame([$staleTunnelUrl], $result['removed']);
        $this->assertSame([$salebotUrl], $result['preserved']);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) use ($staleTunnelUrl): bool {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), 'url='.rawurlencode($staleTunnelUrl));
        });
    }
}
