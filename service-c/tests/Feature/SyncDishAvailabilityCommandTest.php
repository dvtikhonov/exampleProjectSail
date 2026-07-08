<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Food\DishAvailabilitySyncService;
use App\Services\Max\UiStand\MaxMenuAvailabilityNotifier;
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

        $notifier = $this->createMock(MaxMenuAvailabilityNotifier::class);
        $notifier
            ->expects($this->once())
            ->method('notify');

        $this->app->instance(DishAvailabilitySyncService::class, $syncService);
        $this->app->instance(MaxMenuAvailabilityNotifier::class, $notifier);

        $exitCode = Artisan::call('food:sync-dish-availability');

        $this->assertSame(0, $exitCode);
    }
}
