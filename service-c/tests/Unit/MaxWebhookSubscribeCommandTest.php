<?php

namespace Tests\Unit;

use App\Contracts\Max\MaxWebhookStaleDevTunnelCleanerInterface;
use App\Contracts\Max\MaxWebhookSubscriptionClientInterface;
use App\Contracts\Max\MaxWebhookUrlProbeInterface;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class MaxWebhookSubscribeCommandTest extends TestCase
{
    /** Команда регистрирует подписку через узкие порты. */
    public function test_command_registers_subscription_via_subscriber(): void
    {
        config(['max.webhook.url' => '']);

        $subscriptionClient = $this->createMock(MaxWebhookSubscriptionClientInterface::class);
        $subscriptionClient->expects($this->once())->method('subscribe');

        $urlProbe = $this->createMock(MaxWebhookUrlProbeInterface::class);
        $urlProbe->method('probeWebhookUrl')->willReturn([
            'url' => '',
            'http_status' => 200,
            'reachable' => true,
            'error' => null,
        ]);

        $staleCleaner = $this->createMock(MaxWebhookStaleDevTunnelCleanerInterface::class);
        $staleCleaner->expects($this->never())->method('unsubscribeStaleDevTunnels');

        $this->app->instance(MaxWebhookSubscriptionClientInterface::class, $subscriptionClient);
        $this->app->instance(MaxWebhookUrlProbeInterface::class, $urlProbe);
        $this->app->instance(MaxWebhookStaleDevTunnelCleanerInterface::class, $staleCleaner);

        $exitCode = Artisan::call('max:webhook:subscribe');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            'Подписка MAX webhook зарегистрирована.',
            Artisan::output(),
        );
    }

    /** Команда возвращает ошибку, если subscription client выбросил исключение. */
    public function test_command_returns_failure_when_subscriber_throws(): void
    {
        config(['max.webhook.url' => '']);

        $subscriptionClient = $this->createMock(MaxWebhookSubscriptionClientInterface::class);
        $subscriptionClient->expects($this->once())
            ->method('subscribe')
            ->willThrowException(new RuntimeException('MAX_WEBHOOK_URL не задан в конфигурации.'));

        $this->app->instance(MaxWebhookSubscriptionClientInterface::class, $subscriptionClient);
        $this->app->instance(MaxWebhookUrlProbeInterface::class, $this->createMock(MaxWebhookUrlProbeInterface::class));
        $this->app->instance(
            MaxWebhookStaleDevTunnelCleanerInterface::class,
            $this->createMock(MaxWebhookStaleDevTunnelCleanerInterface::class),
        );

        $exitCode = Artisan::call('max:webhook:subscribe');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'MAX_WEBHOOK_URL не задан в конфигурации.',
            Artisan::output(),
        );
    }
}
