<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Max\UiStand\MaxMenuAvailabilityNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;
use Shared\MaxMessenger\DTO\MaxInlineKeyboardMessageDto;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;
use Tests\Support\MessMaxLogTestHelper;
use Tests\TestCase;

class MaxMenuAvailabilityNotifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-01-01 10:00:00', 'Europe/Moscow'),
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_notify_sends_message_with_order_button_to_ui_stand_chat_ids(): void
    {
        Config::set('max.ui_stand.recipient_chat_ids', [111, 222]);
        Config::set('max.ui_stand.recipient_user_ids', [333]);
        Config::set('max.ui_stand.mini_app_url', 'https://example.test/max-app');
        Config::set('max.bot_user_id', 421816864057);
        Config::set('max.ui_stand.mini_app_button_text', 'Заказ еды');

        $sentMessages = [];
        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendInlineKeyboardMessage')
            ->willReturnCallback(function (MaxInlineKeyboardMessageDto $message) use (&$sentMessages): void {
                $sentMessages[] = $message;
            });
        $client->expects($this->never())->method('sendMessage');

        $this->app->instance(MaxMessengerClientInterface::class, $client);
        $this->app->make(MaxMenuAvailabilityNotifier::class)->notify();

        $this->assertCount(2, $sentMessages);
        $this->assertSame([111, 222], array_map(
            static fn (MaxInlineKeyboardMessageDto $dto): ?int => $dto->chatId,
            $sentMessages,
        ));
        $this->assertSame('Доступно для заказов меню на 2.01.2026', $sentMessages[0]->text);
        $this->assertSame($sentMessages[0]->text, $sentMessages[1]->text);

        $button = $sentMessages[0]->buttonRows[0][0];
        $this->assertSame('Заказ еды', $button->text);
        $this->assertSame('open_app', $button->type);
        $this->assertSame('https://example.test/max-app', $button->webApp);
        $this->assertSame(421816864057, $button->contactId);
    }

    public function test_notify_skips_send_when_chat_recipients_are_not_configured(): void
    {
        Config::set('max.ui_stand.recipient_chat_ids', []);

        $captured = [];
        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client->expects($this->never())->method('sendInlineKeyboardMessage');
        $client->expects($this->never())->method('sendMessage');

        $this->app->instance(MaxMessengerClientInterface::class, $client);
        $this->app->make(MaxMenuAvailabilityNotifier::class)->notify();

        $log = MessMaxLogTestHelper::assertSingleMessage(
            $captured,
            'MAX menu availability notification skipped: chat recipients are not configured',
        );
        $this->assertSame('warning', $log->level);
    }

    public function test_notify_logs_warning_and_continues_when_one_chat_fails(): void
    {
        Config::set('max.ui_stand.recipient_chat_ids', [111, 222]);
        Config::set('max.ui_stand.mini_app_url', 'https://example.test/max-app');

        $captured = [];
        Log::channel('messMax')->listen(function (MessageLogged $event) use (&$captured): void {
            $captured[] = $event;
        });

        $client = $this->createMock(MaxMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendInlineKeyboardMessage')
            ->willReturnCallback(function (MaxInlineKeyboardMessageDto $message): void {
                if ($message->chatId === 111) {
                    throw new MaxMessengerRequestException('Chat not found');
                }
            });

        $this->app->instance(MaxMessengerClientInterface::class, $client);
        $this->app->make(MaxMenuAvailabilityNotifier::class)->notify();

        $log = MessMaxLogTestHelper::assertSingleMessage(
            $captured,
            'MAX menu availability notification send failed',
        );
        $this->assertSame('warning', $log->level);
        $this->assertSame(111, $log->context['chat_id']);
        $this->assertSame('Chat not found', $log->context['error']);
    }
}
