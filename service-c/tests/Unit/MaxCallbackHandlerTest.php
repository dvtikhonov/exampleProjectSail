<?php

namespace Tests\Unit;

use App\DTO\Max\MaxCallbackUpdateDto;
use App\Services\Max\UiStand\MaxCallbackHandler;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MaxCallbackHandlerTest extends TestCase
{
    private const TOKEN = 'secret-max-token-for-callback-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'max.bot_access_token' => self::TOKEN,
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
            'max.ui_stand.button_yes_payload' => 'yes',
            'max.ui_stand.button_no_payload' => 'no',
            'logging.channels.stack.channels' => ['single'],
        ]);
    }

    public function test_yes_payload_logs_da_and_answers_callback(): void
    {
        $captured = [];
        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake([
            'platform-api.max.ru/*' => Http::response([], 200),
        ]);

        $this->app->make(MaxCallbackHandler::class)->handle(new MaxCallbackUpdateDto(
            callbackId: 'cb-yes-1',
            payload: 'yes',
            userId: 42,
        ));

        $log = $this->findButtonClickLog($captured);

        $this->assertSame('info', $log->level);
        $this->assertSame('да', $log->context['answer'] ?? null);
        $this->assertSame('yes', $log->context['payload'] ?? null);
        $this->assertSame('cb-yes-1', $log->context['callback_id'] ?? null);
        $this->assertSame(42, $log->context['user_id'] ?? null);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/answers')
                && str_contains($request->url(), 'callback_id=cb-yes-1')
                && $request->hasHeader('Authorization', self::TOKEN)
                && ($request['message']['text'] ?? null) === 'Вы нажали кнопку: да'
                && ! isset($request['notification']);
        });
    }

    public function test_no_payload_logs_net_and_answers_callback(): void
    {
        $captured = [];
        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake([
            'platform-api.max.ru/*' => Http::response([], 200),
        ]);

        $this->app->make(MaxCallbackHandler::class)->handle(new MaxCallbackUpdateDto(
            callbackId: 'cb-no-1',
            payload: 'no',
            userId: 99,
        ));

        $log = $this->findButtonClickLog($captured);

        $this->assertSame('нет', $log->context['answer'] ?? null);
        $this->assertSame('no', $log->context['payload'] ?? null);
        $this->assertSame('cb-no-1', $log->context['callback_id'] ?? null);
        $this->assertSame(99, $log->context['user_id'] ?? null);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'callback_id=cb-no-1')
                && ($request['message']['text'] ?? null) === 'Вы нажали кнопку: нет'
                && ! isset($request['notification']);
        });
    }

    /**
     * @param  list<MessageLogged>  $captured
     */
    private function findButtonClickLog(array $captured): MessageLogged
    {
        $buttonLogs = array_values(array_filter(
            $captured,
            static fn (MessageLogged $event): bool => $event->message === 'MAX button clicked',
        ));

        $this->assertCount(1, $buttonLogs);

        return $buttonLogs[0];
    }
}
