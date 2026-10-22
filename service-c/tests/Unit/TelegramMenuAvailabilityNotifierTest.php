<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Max\MaxUserDeliveryRepositoryInterface;
use App\Contracts\Telegram\TelegramMenuAvailabilityNotifierInterface;
use App\Contracts\Telegram\TelegramMessengerClientInterface;
use App\Enums\MessengerChannel;
use App\Services\Food\Menu\MenuAvailabilityClientNotificationText;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TelegramMenuAvailabilityNotifierTest extends TestCase
{
    private const TOKEN = 'telegram-bot-token-for-menu-availability-test';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.bot_token' => self::TOKEN,
            'telegram.report.chat_ids' => [111],
            'telegram.report.user_ids' => [222],
        ]);

        $maxUserRepository = $this->createMock(MaxUserDeliveryRepositoryInterface::class);
        $maxUserRepository
            ->method('listExternalUserIdsWithDeliveryAddress')
            ->willReturn([]);

        $this->app->instance(MaxUserDeliveryRepositoryInterface::class, $maxUserRepository);
    }

    /** Notify шлёт TELEGRAM_REPORT_* с датой из аргумента. */
    public function test_notify_posts_to_report_recipients(): void
    {
        $menuDate = CarbonImmutable::parse('2026-08-01', 'Europe/Moscow');
        $expectedText = MenuAvailabilityClientNotificationText::forDate($menuDate);
        $this->assertSame('Доступно для заказов меню на 01.08.2026', $expectedText);

        $sentChatIds = [];
        $client = $this->createMock(TelegramMessengerClientInterface::class);
        $client
            ->expects($this->exactly(2))
            ->method('sendMessage')
            ->willReturnCallback(function (int $chatId, string $text) use (&$sentChatIds, $expectedText): void {
                $this->assertSame($expectedText, $text);
                $sentChatIds[] = $chatId;
            });
        $this->app->instance(TelegramMessengerClientInterface::class, $client);

        $sentCount = $this->app->make(TelegramMenuAvailabilityNotifierInterface::class)->notify($menuDate);

        $this->assertSame(2, $sentCount);
        $this->assertSame([111, 222], $sentChatIds);
    }

    /** Notify шлёт telegram-пользователям с адресом доставки. */
    public function test_notify_posts_to_telegram_users_with_delivery_address(): void
    {
        config([
            'telegram.report.chat_ids' => [],
            'telegram.report.user_ids' => [],
        ]);

        $maxUserRepository = $this->createMock(MaxUserDeliveryRepositoryInterface::class);
        $maxUserRepository
            ->expects($this->once())
            ->method('listExternalUserIdsWithDeliveryAddress')
            ->with(MessengerChannel::Telegram)
            ->willReturn([333]);
        $this->app->instance(MaxUserDeliveryRepositoryInterface::class, $maxUserRepository);

        $sentChatIds = [];
        $client = $this->createMock(TelegramMessengerClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendMessage')
            ->willReturnCallback(function (int $chatId) use (&$sentChatIds): void {
                $sentChatIds[] = $chatId;
            });
        $this->app->instance(TelegramMessengerClientInterface::class, $client);

        $sentCount = $this->app->make(TelegramMenuAvailabilityNotifierInterface::class)
            ->notify(CarbonImmutable::parse('2026-08-01', 'Europe/Moscow'));

        $this->assertSame(1, $sentCount);
        $this->assertSame([333], $sentChatIds);
    }

    /** Notify дедуплицирует настроенных и пользователей с адресом. */
    public function test_notify_deduplicates_configured_and_delivery_address_users(): void
    {
        config([
            'telegram.report.chat_ids' => [],
            'telegram.report.user_ids' => [222],
        ]);

        $maxUserRepository = $this->createMock(MaxUserDeliveryRepositoryInterface::class);
        $maxUserRepository
            ->method('listExternalUserIdsWithDeliveryAddress')
            ->willReturn([222, 333]);
        $this->app->instance(MaxUserDeliveryRepositoryInterface::class, $maxUserRepository);

        $client = $this->createMock(TelegramMessengerClientInterface::class);
        $client->expects($this->exactly(2))->method('sendMessage');
        $this->app->instance(TelegramMessengerClientInterface::class, $client);

        $sentCount = $this->app->make(TelegramMenuAvailabilityNotifierInterface::class)
            ->notify(CarbonImmutable::parse('2026-08-01', 'Europe/Moscow'));

        $this->assertSame(2, $sentCount);
    }

    /** Notify пропускает отправку, если получатели отсутствуют. */
    public function test_notify_skips_when_recipients_are_missing(): void
    {
        config([
            'telegram.report.chat_ids' => [],
            'telegram.report.user_ids' => [],
        ]);

        $client = $this->createMock(TelegramMessengerClientInterface::class);
        $client->expects($this->never())->method('sendMessage');
        $this->app->instance(TelegramMessengerClientInterface::class, $client);

        $sentCount = $this->app->make(TelegramMenuAvailabilityNotifierInterface::class)
            ->notify(CarbonImmutable::parse('2026-08-01', 'Europe/Moscow'));

        $this->assertSame(0, $sentCount);
    }

    /** Notify пропускает отправку, если бот не настроен. */
    public function test_notify_skips_when_bot_is_not_configured(): void
    {
        config(['telegram.bot_token' => '']);

        $client = $this->createMock(TelegramMessengerClientInterface::class);
        $client->expects($this->never())->method('sendMessage');
        $this->app->instance(TelegramMessengerClientInterface::class, $client);

        $sentCount = $this->app->make(TelegramMenuAvailabilityNotifierInterface::class)
            ->notify(CarbonImmutable::parse('2026-08-01', 'Europe/Moscow'));

        $this->assertSame(0, $sentCount);
    }
}
