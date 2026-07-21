<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\DailyMenuLineCollectorInterface;
use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Contracts\Food\MaxManagerDailyMenuMessageBuilderInterface;
use App\Contracts\Max\MaxManagerDailyMenuNotifierInterface;
use App\DTO\Food\MaxManagerDailyMenuMessagesDto;
use App\Enums\Food\FoodOrderAdminRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaxManagerDailyMenuNotifierTest extends TestCase
{
    private const TOKEN = 'secret-max-token-for-manager-menu-test';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'max.bot_access_token' => self::TOKEN,
            'max.bot_username' => 'food_test_bot',
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
            'max.ui_stand.recipient_chat_ids' => [],
            'max.ui_stand.recipient_user_ids' => [],
        ]);

        Cache::flush();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-07-21 03:00:00', 'Europe/Moscow'),
        );

        $collector = $this->createMock(DailyMenuLineCollectorInterface::class);
        $collector->method('collect')->willReturn([]);
        $this->app->instance(DailyMenuLineCollectorInterface::class, $collector);

        $builder = $this->createMock(MaxManagerDailyMenuMessageBuilderInterface::class);
        $builder->method('build')->willReturn(new MaxManagerDailyMenuMessagesDto(
            withoutDelivery: 'menu-without-delivery',
            withDelivery: 'menu-with-delivery',
        ));
        $this->app->instance(MaxManagerDailyMenuMessageBuilderInterface::class, $builder);
    }

    /** Notify шлёт по два сообщения каждому max_manager. */
    public function test_notify_sends_two_messages_to_each_max_manager(): void
    {
        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository
            ->expects($this->once())
            ->method('listActiveMaxUserIdsByRole')
            ->with(FoodOrderAdminRole::MaxManager)
            ->willReturn([1006, 1007]);
        $this->app->instance(FoodOrderAdminRepositoryInterface::class, $adminRepository);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $sentCount = $this->app->make(MaxManagerDailyMenuNotifierInterface::class)->notify();

        $this->assertSame(4, $sentCount);
        Http::assertSentCount(4);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://platform-api.max.ru/messages?user_id=1006'
            && $request['text'] === 'menu-without-delivery');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://platform-api.max.ru/messages?user_id=1006'
            && $request['text'] === 'menu-with-delivery');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://platform-api.max.ru/messages?user_id=1007'
            && $request['text'] === 'menu-without-delivery');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://platform-api.max.ru/messages?user_id=1007'
            && $request['text'] === 'menu-with-delivery');
    }

    /** Если DM менеджеру недоступен — оба текста уходят в UI Stand chat (как «Заказ на»). */
    public function test_notify_falls_back_to_ui_stand_when_manager_dm_fails(): void
    {
        config([
            'max.ui_stand.recipient_chat_ids' => [-75495934087316],
            'max.ui_stand.recipient_user_ids' => [],
        ]);

        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository
            ->method('listActiveMaxUserIdsByRole')
            ->willReturn([1003]);
        $this->app->instance(FoodOrderAdminRepositoryInterface::class, $adminRepository);

        Http::fake([
            'platform-api.max.ru/messages?user_id=1003' => Http::response([
                'code' => 'chat.not.found',
                'message' => 'Chat with user 1003 not found',
            ], 404),
            'platform-api.max.ru/messages?chat_id=-75495934087316' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $sentCount = $this->app->make(MaxManagerDailyMenuNotifierInterface::class)->notify();

        $this->assertSame(2, $sentCount);
        Http::assertSentCount(4);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://platform-api.max.ru/messages?user_id=1003'
            && $request['text'] === 'menu-without-delivery');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://platform-api.max.ru/messages?user_id=1003'
            && $request['text'] === 'menu-with-delivery');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://platform-api.max.ru/messages?chat_id=-75495934087316'
            && $request['text'] === 'menu-without-delivery');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://platform-api.max.ru/messages?chat_id=-75495934087316'
            && $request['text'] === 'menu-with-delivery');
    }

    /** Notify пропускает отправку без активных max_manager. */
    public function test_notify_skips_when_no_max_managers(): void
    {
        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository
            ->method('listActiveMaxUserIdsByRole')
            ->willReturn([]);
        $this->app->instance(FoodOrderAdminRepositoryInterface::class, $adminRepository);

        Http::fake();

        $sentCount = $this->app->make(MaxManagerDailyMenuNotifierInterface::class)->notify();

        $this->assertSame(0, $sentCount);
        Http::assertNothingSent();
    }

    /** Notify пропускает отправку, если бот не настроен. */
    public function test_notify_skips_when_bot_is_not_configured(): void
    {
        config(['max.bot_username' => '']);

        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository
            ->expects($this->never())
            ->method('listActiveMaxUserIdsByRole');
        $this->app->instance(FoodOrderAdminRepositoryInterface::class, $adminRepository);

        Http::fake();

        $sentCount = $this->app->make(MaxManagerDailyMenuNotifierInterface::class)->notify();

        $this->assertSame(0, $sentCount);
        Http::assertNothingSent();
    }
}
