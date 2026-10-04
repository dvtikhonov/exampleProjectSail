<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\MaxIncomingRelay;

use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\DTO\Max\MaxUserRecord;
use App\Modules\MaxIncomingRelay\Contracts\BotDmMessageRepositoryInterface;
use App\Modules\MaxIncomingRelay\Contracts\CustomerLastOrderRepositoryInterface;
use App\Modules\MaxIncomingRelay\Contracts\IncomingMessageRelayServiceInterface;
use App\Modules\MaxIncomingRelay\DTO\BotDmMessageRecord;
use App\Modules\MaxIncomingRelay\DTO\IncomingBotMessageDto;
use App\Modules\MaxIncomingRelay\DTO\LastOrderSummaryDto;
use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

/**
 * Unit: IncomingMessageRelayService — лог + рассылка в configuredChatIds + persist для max_users.
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

        $this->app->instance(
            MaxUserIdentityRepositoryInterface::class,
            $this->createMock(MaxUserIdentityRepositoryInterface::class),
        );
        $this->app->instance(
            BotDmMessageRepositoryInterface::class,
            $this->createMock(BotDmMessageRepositoryInterface::class),
        );
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

    /** Known max_users + непустой text → persist customer; Home_chat всё равно уходит. */
    public function test_relay_persists_customer_message_for_known_max_user_with_non_empty_text(): void
    {
        $this->stubLastOrderRepository(null);

        $identity = $this->createMock(MaxUserIdentityRepositoryInterface::class);
        $identity->expects($this->once())
            ->method('findByMaxUserId')
            ->with(54321)
            ->willReturn($this->maxUserRecord(54321));
        $this->app->instance(MaxUserIdentityRepositoryInterface::class, $identity);

        $botDm = $this->createMock(BotDmMessageRepositoryInterface::class);
        $botDm->expects($this->once())
            ->method('create')
            ->with(
                54321,
                54321,
                BotDmAuthorType::Customer,
                'Здравствуйте, хочу заказ',
                -100000000,
            )
            ->willReturn($this->botDmMessageRecord());
        $this->app->instance(BotDmMessageRepositoryInterface::class, $botDm);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $this->app->make(IncomingMessageRelayServiceInterface::class)->relay(
            new IncomingBotMessageDto(
                userId: 54321,
                firstName: 'Иван',
                lastName: 'Петров',
                text: '  Здравствуйте, хочу заказ  ',
                timestampMs: 1_790_152_800_000,
                chatId: -100000000,
            ),
        );

        Http::assertSentCount(2);
    }

    /** Пользователь вне max_users → без persist; Home_chat вызывается. */
    public function test_relay_skips_persist_for_unknown_max_user_but_still_sends_home_chat(): void
    {
        $this->stubLastOrderRepository(null);

        $identity = $this->createMock(MaxUserIdentityRepositoryInterface::class);
        $identity->expects($this->once())
            ->method('findByMaxUserId')
            ->with(999001)
            ->willReturn(null);
        $this->app->instance(MaxUserIdentityRepositoryInterface::class, $identity);

        $botDm = $this->createMock(BotDmMessageRepositoryInterface::class);
        $botDm->expects($this->never())->method('create');
        $this->app->instance(BotDmMessageRepositoryInterface::class, $botDm);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $this->app->make(IncomingMessageRelayServiceInterface::class)->relay(
            new IncomingBotMessageDto(
                userId: 999001,
                firstName: 'Гость',
                lastName: '',
                text: 'Привет от неизвестного',
                timestampMs: 1_790_152_800_000,
                chatId: -100000000,
            ),
        );

        Http::assertSentCount(2);
    }

    /** Пустой/пробельный text → без persist; Home_chat вызывается. */
    public function test_relay_skips_persist_for_blank_text_but_still_sends_home_chat(): void
    {
        $this->stubLastOrderRepository(null);

        $identity = $this->createMock(MaxUserIdentityRepositoryInterface::class);
        $identity->expects($this->never())->method('findByMaxUserId');
        $this->app->instance(MaxUserIdentityRepositoryInterface::class, $identity);

        $botDm = $this->createMock(BotDmMessageRepositoryInterface::class);
        $botDm->expects($this->never())->method('create');
        $this->app->instance(BotDmMessageRepositoryInterface::class, $botDm);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $this->app->make(IncomingMessageRelayServiceInterface::class)->relay(
            new IncomingBotMessageDto(
                userId: 54321,
                firstName: 'Иван',
                lastName: 'Петров',
                text: "  \t  ",
                timestampMs: 1_790_152_800_000,
                chatId: -100000000,
            ),
        );

        Http::assertSentCount(2);
    }

    private function stubLastOrderRepository(?LastOrderSummaryDto $lastOrder): void
    {
        $this->app->instance(
            CustomerLastOrderRepositoryInterface::class,
            new class($lastOrder) implements CustomerLastOrderRepositoryInterface
            {
                public function __construct(private readonly ?LastOrderSummaryDto $lastOrder) {}

                public function findLatestByMaxUserId(int $maxUserId): ?LastOrderSummaryDto
                {
                    return $this->lastOrder;
                }
            },
        );
    }

    private function maxUserRecord(int $maxUserId): MaxUserRecord
    {
        return new MaxUserRecord(
            maxUserId: $maxUserId,
            firstName: 'Иван',
            lastName: 'Петров',
            username: null,
            languageCode: null,
            photoUrl: null,
            aiAccessUntil: null,
            customerCategoryId: null,
            deliveryAddress: null,
        );
    }

    private function botDmMessageRecord(): BotDmMessageRecord
    {
        return new BotDmMessageRecord(
            id: 1,
            maxUserId: 54321,
            senderMaxUserId: 54321,
            authorType: BotDmAuthorType::Customer,
            body: 'Здравствуйте, хочу заказ',
            chatId: -100000000,
            createdAt: '2026-09-23T11:40:00+00:00',
            senderFirstName: 'Иван',
            senderLastName: 'Петров',
            senderUsername: null,
        );
    }
}
