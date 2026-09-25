<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MaxIncomingRelay;

use App\Modules\MaxIncomingRelay\Contracts\CustomerLastOrderRepositoryInterface;
use App\Modules\MaxIncomingRelay\Contracts\IncomingMessageRelayServiceInterface;
use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;
use App\Modules\MaxIncomingRelay\DTO\LastOrderSummaryDto;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

/**
 * Unit: IncomingMessageRelayService — лог + рассылка только в configuredChatIds.
 */
final class IncomingMessageRelayServiceTest extends TestCase
{
    private const string TOKEN = 'secret-max-token-for-incoming-relay-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'max.messenger_driver' => 'http',
            'max.bot_access_token' => self::TOKEN,
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
            'max.ui_stand.recipient_chat_ids' => [-1001, -1002],
            'max.ui_stand.recipient_user_ids' => [99999],
            'logging.channels.stack.channels' => ['single'],
        ]);
    }

    /** Http::fake: N вызовов send на chat_ids из config; лог = тот же text; USER_IDS не трогаем. */
    public function test_relay_sends_to_configured_chat_ids_and_logs_same_text(): void
    {
        $this->app->instance(
            CustomerLastOrderRepositoryInterface::class,
            new class implements CustomerLastOrderRepositoryInterface
            {
                public function findLatestByMaxUserId(int $maxUserId): ?LastOrderSummaryDto
                {
                    return new LastOrderSummaryDto(
                        id: 1842,
                        createdAt: new DateTimeImmutable('2026-09-20 12:00:00', new DateTimeZone('Europe/Moscow')),
                    );
                }
            },
        );

        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $message = new IncomingBotMessageDto(
            userId: 54321,
            firstName: 'Иван',
            lastName: 'Петров',
            text: 'Здравствуйте, хочу заказ',
            timestampMs: 1_790_152_800_000,
            chatId: -100000000,
        );

        $this->app->make(IncomingMessageRelayServiceInterface::class)->relay($message);

        $expectedText = implode("\n", [
            'Получено сообщение от user_id 54321 Иван Петров',
            'текст сообщения: Здравствуйте, хочу заказ',
            'Дата и время: 23.09.2026 11:40',
            'Дата и номер последнего заказа: 20.09.2026 №1842',
        ]);

        $relayLog = MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX incoming message relay');
        $this->assertSame('warning', $relayLog->level);
        $this->assertSame(54321, $relayLog->context['user_id'] ?? null);
        $this->assertSame([-1001, -1002], $relayLog->context['chat_ids'] ?? null);
        $this->assertSame($expectedText, $relayLog->context['text'] ?? null);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) use ($expectedText): bool {
            return str_contains($request->url(), 'chat_id=-1001')
                && ($request['text'] ?? null) === $expectedText
                && ! isset($request['attachments']);
        });
        Http::assertSent(function ($request) use ($expectedText): bool {
            return str_contains($request->url(), 'chat_id=-1002')
                && ($request['text'] ?? null) === $expectedText;
        });
        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), 'user_id=99999');
        });
    }

    /** Пустые chat_ids → warning, без HTTP. */
    public function test_relay_warns_when_chat_ids_empty(): void
    {
        config(['max.ui_stand.recipient_chat_ids' => []]);

        $this->app->instance(
            CustomerLastOrderRepositoryInterface::class,
            new class implements CustomerLastOrderRepositoryInterface
            {
                public function findLatestByMaxUserId(int $maxUserId): ?LastOrderSummaryDto
                {
                    return null;
                }
            },
        );

        $captured = [];
        Log::channel('max_log')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        Http::fake();

        $this->app->make(IncomingMessageRelayServiceInterface::class)->relay(
            new IncomingBotMessageDto(
                userId: 54321,
                firstName: '',
                lastName: '',
                text: 'Привет',
                timestampMs: 1_790_152_860_000,
                chatId: null,
            ),
        );

        MessMaxLogTestHelper::assertSingleMessage($captured, 'MAX incoming message relay');
        $warning = MessMaxLogTestHelper::assertSingleMessage(
            $captured,
            'MAX incoming message relay skipped: MAX_UI_STAND_CHAT_IDS is empty',
        );
        $this->assertSame('warning', $warning->level);
        Http::assertNothingSent();
    }
}
