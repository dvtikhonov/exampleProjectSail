<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Services\Max\UiStand\MaxMenuAvailabilityNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaxMenuAvailabilityNotifierTest extends TestCase
{
    private const TOKEN = 'secret-max-token-for-menu-availability-test';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'max.bot_access_token' => self::TOKEN,
            'max.bot_username' => 'food_test_bot',
            'max.order_notifications.chat_ids' => [111],
            'max.order_notifications.user_ids' => [222],
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
        ]);

        $maxUserRepository = $this->createMock(MaxUserRepositoryInterface::class);
        $maxUserRepository
            ->method('listMaxUserIdsWithDeliveryAddress')
            ->willReturn([]);

        $this->app->instance(MaxUserRepositoryInterface::class, $maxUserRepository);
    }

    /** Notify шлёт получателям уведомлений о заказах, как тестовый бот. */
    public function test_notify_posts_to_order_notification_recipients_like_test_bot(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-07-09 03:00:00', 'Europe/Moscow'),
        );

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $sentCount = $this->app->make(MaxMenuAvailabilityNotifierInterface::class)->notify();

        $this->assertSame(2, $sentCount);

        $expectedText = MaxMenuAvailabilityNotifier::messageTextForDate(
            CarbonImmutable::parse('2026-07-09', 'Europe/Moscow'),
        );
        $this->assertSame('Доступно для заказов меню на 09.07.2026', $expectedText);

        Http::assertSentCount(2);
        Http::assertSent(function ($request) use ($expectedText): bool {
            return $request->url() === 'https://platform-api.max.ru/messages?chat_id=111'
                && $request['text'] === $expectedText;
        });
        Http::assertSent(function ($request) use ($expectedText): bool {
            return $request->url() === 'https://platform-api.max.ru/messages?user_id=222'
                && $request['text'] === $expectedText;
        });
    }

    /** Notify шлёт пользователям с адресом доставки. */
    public function test_notify_posts_to_users_with_delivery_address(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-07-09 03:00:00', 'Europe/Moscow'),
        );

        config([
            'max.order_notifications.chat_ids' => [],
            'max.order_notifications.user_ids' => [],
        ]);

        $maxUserRepository = $this->createMock(MaxUserRepositoryInterface::class);
        $maxUserRepository
            ->method('listMaxUserIdsWithDeliveryAddress')
            ->willReturn([333]);
        $this->app->instance(MaxUserRepositoryInterface::class, $maxUserRepository);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $sentCount = $this->app->make(MaxMenuAvailabilityNotifierInterface::class)->notify();

        $this->assertSame(1, $sentCount);
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://platform-api.max.ru/messages?user_id=333';
        });
    }

    /** Notify дедуплицирует настроенных и пользователей с адресом. */
    public function test_notify_deduplicates_configured_and_delivery_address_users(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-07-09 03:00:00', 'Europe/Moscow'),
        );

        config([
            'max.order_notifications.chat_ids' => [],
            'max.order_notifications.user_ids' => [222],
        ]);

        $maxUserRepository = $this->createMock(MaxUserRepositoryInterface::class);
        $maxUserRepository
            ->method('listMaxUserIdsWithDeliveryAddress')
            ->willReturn([222, 333]);
        $this->app->instance(MaxUserRepositoryInterface::class, $maxUserRepository);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $sentCount = $this->app->make(MaxMenuAvailabilityNotifierInterface::class)->notify();

        $this->assertSame(2, $sentCount);
        Http::assertSentCount(2);
    }

    /** Notify пропускает отправку, если получатели отсутствуют. */
    public function test_notify_skips_when_recipients_are_missing(): void
    {
        config([
            'max.order_notifications.chat_ids' => [],
            'max.order_notifications.user_ids' => [],
        ]);

        Http::fake();

        $sentCount = $this->app->make(MaxMenuAvailabilityNotifierInterface::class)->notify();

        $this->assertSame(0, $sentCount);
        Http::assertNothingSent();
    }

    /** Notify пропускает отправку, если бот не настроен. */
    public function test_notify_skips_when_bot_is_not_configured(): void
    {
        config(['max.bot_username' => '']);

        Http::fake();

        $sentCount = $this->app->make(MaxMenuAvailabilityNotifierInterface::class)->notify();

        $this->assertSame(0, $sentCount);
        Http::assertNothingSent();
    }
}
