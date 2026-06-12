<?php

namespace Tests\Unit;

use App\Services\Max\UiStand\MaxWebhookUpdateRouter;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MaxWebhookUpdateRouterTest extends TestCase
{
    private const TOKEN = 'secret-max-token-for-webhook-router-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'max.bot_access_token' => self::TOKEN,
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
            'max.ui_stand.greeting_text' => 'Привет! Выберите ответ:',
            'max.ui_stand.button_yes_payload' => 'yes',
            'max.ui_stand.button_no_payload' => 'no',
            'logging.channels.stack.channels' => ['single'],
        ]);
    }

    public function test_bot_started_sends_greeting_only_to_event_user(): void
    {
        $captured = [];
        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $this->app->make(MaxWebhookUpdateRouter::class)->handle([
            'update_type' => 'bot_started',
            'user' => ['user_id' => 777],
        ]);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'user_id=777');
        });

        $webhookLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX webhook received',
        ));
        $this->assertCount(1, $webhookLogs);
        $this->assertSame('bot_started', $webhookLogs[0]->context['update_type'] ?? null);
    }
}
