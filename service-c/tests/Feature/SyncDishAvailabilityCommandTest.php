<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Max\MaxManagerDailyMenuNotifierInterface;
use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Services\Food\DishAvailabilitySyncService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncDishAvailabilityCommandTest extends TestCase
{
    /** Команда синхронизирует доступность и шлёт уведомления о меню. */
    public function test_command_syncs_availability_and_sends_menu_notification(): void
    {
        $syncService = $this->createMock(DishAvailabilitySyncService::class);
        $syncService
            ->expects($this->once())
            ->method('syncForToday')
            ->willReturn(5);

        $notifier = $this->createMock(MaxMenuAvailabilityNotifierInterface::class);
        $notifier
            ->expects($this->once())
            ->method('notify')
            ->willReturn(1);

        $managerNotifier = $this->createMock(MaxManagerDailyMenuNotifierInterface::class);
        $managerNotifier
            ->expects($this->once())
            ->method('notify')
            ->willReturn(2);

        $this->app->instance(DishAvailabilitySyncService::class, $syncService);
        $this->app->instance(MaxMenuAvailabilityNotifierInterface::class, $notifier);
        $this->app->instance(MaxManagerDailyMenuNotifierInterface::class, $managerNotifier);

        $exitCode = Artisan::call('food:sync-dish-availability');

        $this->assertSame(0, $exitCode);
    }
}
