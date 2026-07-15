<?php

namespace Tests\Unit;

use App\Services\Max\UiStand\MaxWebhookSubscriber;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class MaxWebhookSubscribeCommandTest extends TestCase
{
    /** Команда регистрирует подписку через subscriber. */
    public function test_command_registers_subscription_via_subscriber(): void
    {
        config(['max.webhook.url' => '']);

        $subscriber = $this->createMock(MaxWebhookSubscriber::class);
        $subscriber->expects($this->once())->method('subscribe');
        $subscriber->method('probeWebhookUrl')->willReturn([
            'url' => '',
            'http_status' => 200,
            'reachable' => true,
            'error' => null,
        ]);

        $this->app->instance(MaxWebhookSubscriber::class, $subscriber);

        $exitCode = Artisan::call('max:webhook:subscribe');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            'Подписка MAX webhook зарегистрирована.',
            Artisan::output(),
        );
    }

    /** Команда возвращает ошибку, если subscriber выбросил исключение. */
    public function test_command_returns_failure_when_subscriber_throws(): void
    {
        config(['max.webhook.url' => '']);

        $subscriber = $this->createMock(MaxWebhookSubscriber::class);
        $subscriber->expects($this->once())
            ->method('subscribe')
            ->willThrowException(new RuntimeException('MAX_WEBHOOK_URL не задан в конфигурации.'));

        $this->app->instance(MaxWebhookSubscriber::class, $subscriber);

        $exitCode = Artisan::call('max:webhook:subscribe');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'MAX_WEBHOOK_URL не задан в конфигурации.',
            Artisan::output(),
        );
    }
}
