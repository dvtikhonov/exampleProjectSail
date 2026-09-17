<?php

namespace Tests\Feature;

use App\Contracts\Max\MaxWebhookUpdateRouterInterface;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class MaxWebhookControllerTest extends TestCase
{
    private const SECRET = 'test-webhook-secret';

    private const TOKEN = 'secret-max-token-for-webhook-feature-tests';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'max.webhook.secret' => self::SECRET,
            'max.ui_stand.button_yes_payload' => 'yes',
            'max.ui_stand.button_no_payload' => 'no',
            'max.bot_access_token' => self::TOKEN,
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
            'logging.channels.stack.channels' => ['single'],
        ]);
    }

    /** Message callback пишет в mess_max лог и возвращает OK. */
    public function test_message_callback_writes_to_mess_max_log_and_returns_ok(): void
    {
        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake([
            'platform-api.max.ru/*' => Http::response([], 200),
        ]);

        $response = $this->postJson('/api/webhooks/max', $this->messageCallbackPayload(
            callbackId: 'cb-feature-1',
            payload: 'yes',
            userId: 54321,
        ), [
            'X-Max-Bot-Api-Secret' => self::SECRET,
        ]);

        $response->assertOk();
        $this->assertSame('', $response->getContent());

        $requestLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX webhook request received.',
        ));
        $this->assertCount(1, $requestLogs);
        $this->assertSame('message_callback', $requestLogs[0]->context['update_type'] ?? null);

        $webhookLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX webhook received',
        ));
        $this->assertCount(1, $webhookLogs);
        $this->assertSame('message_callback', $webhookLogs[0]->context['update_type'] ?? null);

        $buttonLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX button clicked',
        ));

        $this->assertCount(1, $buttonLogs);
        $this->assertSame('да', $buttonLogs[0]->context['answer'] ?? null);
        $this->assertSame('yes', $buttonLogs[0]->context['payload'] ?? null);
        $this->assertSame('cb-feature-1', $buttonLogs[0]->context['callback_id'] ?? null);
        $this->assertSame(54321, $buttonLogs[0]->context['user_id'] ?? null);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'callback_id=cb-feature-1')
                && ($request['message']['text'] ?? null) === 'Вы нажали кнопку: да';
        });
    }

    /** Возвращает 401 без валидного секрета. */
    public function test_returns_unauthorized_without_valid_secret(): void
    {
        Http::fake();

        $response = $this->postJson('/api/webhooks/max', $this->messageCallbackPayload(
            callbackId: 'cb-feature-2',
            payload: 'no',
            userId: 1,
        ), [
            'X-Max-Bot-Api-Secret' => 'invalid',
        ]);

        $response->assertUnauthorized();
        Http::assertNothingSent();
    }

    /** Мусорный body без update_type → HTTP 200, роутер не вызывается. */
    public function test_garbage_body_returns_ok_and_does_not_invoke_router(): void
    {
        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake();

        $response = $this->call(
            'POST',
            '/api/webhooks/max',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_Max_Bot_Api_Secret' => self::SECRET,
            ],
            'not-json-at-all{{{',
        );

        $response->assertOk();
        $this->assertSame('', $response->getContent());
        Http::assertNothingSent();

        $ignoredLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX webhook payload ignored (invalid or missing update_type).',
        ));
        $this->assertNotEmpty($ignoredLogs);

        $receivedLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX webhook request received.',
        ));
        $this->assertCount(0, $receivedLogs);
    }

    /** Payload без update_type → HTTP 200, роутер не вызывается. */
    public function test_missing_update_type_returns_ok_and_does_not_invoke_router(): void
    {
        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake();

        $response = $this->postJson('/api/webhooks/max', [
            'timestamp' => 1739184000000,
            'callback' => ['callback_id' => 'cb-no-type'],
        ], [
            'X-Max-Bot-Api-Secret' => self::SECRET,
        ]);

        $response->assertOk();
        $this->assertSame('', $response->getContent());
        Http::assertNothingSent();

        $ignoredLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX webhook payload ignored (invalid or missing update_type).',
        ));
        $this->assertNotEmpty($ignoredLogs);

        $webhookLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX webhook received',
        ));
        $this->assertCount(0, $webhookLogs);
    }

    /** Нестроковый update_type → HTTP 200 без обработки. */
    public function test_non_string_update_type_returns_ok_without_routing(): void
    {
        Http::fake();

        $response = $this->postJson('/api/webhooks/max', [
            'update_type' => 42,
            'timestamp' => 1,
        ], [
            'X-Max-Bot-Api-Secret' => self::SECRET,
        ]);

        $response->assertOk();
        Http::assertNothingSent();
    }

    /** Исключение роутера → HTTP 500 и запись в лог (платформа может ретраить). */
    public function test_router_exception_returns_500_and_logs_error(): void
    {
        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $this->mock(MaxWebhookUpdateRouterInterface::class)
            ->shouldReceive('handle')
            ->once()
            ->andThrow(new RuntimeException('router boom'));

        $response = $this->postJson('/api/webhooks/max', [
            'update_type' => 'bot_started',
            'timestamp' => 1739184000000,
            'user' => [
                'user_id' => 1,
            ],
        ], [
            'X-Max-Bot-Api-Secret' => self::SECRET,
        ]);

        $response->assertStatus(500);
        $this->assertSame('', $response->getContent());

        $errorLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX webhook handling failed',
        ));
        $this->assertCount(1, $errorLogs);
        $this->assertSame('error', $errorLogs[0]->level);
        $this->assertSame('router boom', $errorLogs[0]->context['error'] ?? null);
    }

    /** Финальный сбой answerCallback на message_callback → HTTP 500. */
    public function test_callback_answer_failure_returns_500(): void
    {
        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $response = $this->postJson('/api/webhooks/max', $this->messageCallbackPayload(
            callbackId: 'cb-fail-feature-1',
            payload: 'yes',
            userId: 777,
        ), [
            'X-Max-Bot-Api-Secret' => self::SECRET,
        ]);

        $response->assertStatus(500);
        $this->assertSame('', $response->getContent());

        $errorLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX webhook handling failed',
        ));
        $this->assertCount(1, $errorLogs);
        $this->assertSame('error', $errorLogs[0]->level);
    }

    /** POST /api/webhooks/max защищён throttle:60,1. */
    public function test_webhook_route_has_throttle_middleware(): void
    {
        $route = collect(Route::getRoutes())->first(
            static function ($route): bool {
                return $route->uri() === 'api/webhooks/max'
                    && in_array('POST', $route->methods(), true);
            },
        );

        $this->assertNotNull($route);
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
        $this->assertContains('max.webhook.secret', $route->gatherMiddleware());
    }

    /**
     * @return array<string, mixed>
     */
    private function messageCallbackPayload(string $callbackId, string $payload, int $userId): array
    {
        return [
            'update_type' => 'message_callback',
            'timestamp' => 1739184000000,
            'callback' => [
                'callback_id' => $callbackId,
                'payload' => $payload,
                'user' => [
                    'user_id' => $userId,
                ],
            ],
            'message' => [
                'recipient' => [
                    'chat_id' => -100000000,
                    'user_id' => $userId,
                ],
            ],
        ];
    }
}
