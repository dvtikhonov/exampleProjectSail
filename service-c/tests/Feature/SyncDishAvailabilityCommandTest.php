<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Services\Food\DishAvailabilitySyncService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncDishAvailabilityCommandTest extends TestCase
{
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

        $this->app->instance(DishAvailabilitySyncService::class, $syncService);
        $this->app->instance(MaxMenuAvailabilityNotifierInterface::class, $notifier);

        $exitCode = Artisan::call('food:sync-dish-availability');

        $this->assertSame(0, $exitCode);
    }
}
