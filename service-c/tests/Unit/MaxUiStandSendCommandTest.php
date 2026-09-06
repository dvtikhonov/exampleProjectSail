<?php

namespace Tests\Unit;

use App\Contracts\Max\MaxUiStandGreetingSenderInterface;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class MaxUiStandSendCommandTest extends TestCase
{
    /** Команда отправляет приветствие через sender. */
    public function test_command_sends_greeting_via_sender(): void
    {
        $sender = $this->createMock(MaxUiStandGreetingSenderInterface::class);
        $sender->expects($this->once())->method('send');

        $this->app->instance(MaxUiStandGreetingSenderInterface::class, $sender);

        $exitCode = Artisan::call('max:ui-stand:send');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            'Приветствие отправлено',
            Artisan::output(),
        );
    }

    /** Команда возвращает ошибку, если sender выбросил исключение. */
    public function test_command_returns_failure_when_sender_throws(): void
    {
        $sender = $this->createMock(MaxUiStandGreetingSenderInterface::class);
        $sender->expects($this->once())
            ->method('send')
            ->willThrowException(new RuntimeException('MAX UI stand recipients are not configured.'));

        $this->app->instance(MaxUiStandGreetingSenderInterface::class, $sender);

        $exitCode = Artisan::call('max:ui-stand:send');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'MAX UI stand recipients are not configured.',
            Artisan::output(),
        );
    }
}
