<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Shared\LlmCallExchangeDto;
use App\Infrastructure\Laravel\LaravelMaxLogLlmCallLogger;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

/**
 * Запись обмена LLM в канал max_log текстом.
 */
final class LaravelMaxLogLlmCallLoggerTest extends TestCase
{
    public function test_log_exchange_writes_plain_text_to_max_log(): void
    {
        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $logger = new LaravelMaxLogLlmCallLogger(Log::channel('max_log'));
        $logger->logExchange(new LlmCallExchangeDto(
            provider: 'briskly-sync',
            operation: 'match',
            actor: 'max_user_id=99',
            requestText: "=== prompt.system ===\nhello",
            responseText: "=== match_lines ===\n[]",
            meta: [
                'session_id' => 'abc',
                'restaurant_id' => 3,
            ],
        ));

        $log = MessMaxLogTestHelper::assertSingleMessage($captured, 'LLM briskly-sync/match');
        $this->assertSame('info', $log->level);
        $this->assertSame('briskly-sync', $log->context['provider']);
        $this->assertSame('match', $log->context['operation']);
        $this->assertSame('max_user_id=99', $log->context['actor']);
        $this->assertSame('abc', $log->context['session_id']);
        $this->assertSame('3', $log->context['restaurant_id']);
        $this->assertSame("=== prompt.system ===\nhello", $log->context['request']);
        $this->assertSame("=== match_lines ===\n[]", $log->context['response']);
        $this->assertIsString($log->context['request']);
        $this->assertIsString($log->context['response']);
    }
}
