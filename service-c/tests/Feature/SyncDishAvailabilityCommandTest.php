<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\Menu\DishAvailabilitySyncServiceInterface;
use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Max\MaxManagerDailyMenuNotifierInterface;
use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Contracts\Telegram\TelegramMenuAvailabilityNotifierInterface;
use App\DTO\Food\Menu\MenuAvailabilityDateResultDto;
use DateTimeInterface;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncDishAvailabilityCommandTest extends TestCase
{
    /** Команда синхронизирует доступность по offsets категорий и шлёт уведомления. */
    public function test_command_syncs_availability_and_sends_menu_notification(): void
    {
        $dateResolver = $this->createMock(MenuAvailabilityDateResolverInterface::class);
        $dateResolver
            ->expects($this->once())
            ->method('resolveForCurrentWeekday')
            ->willReturn(new MenuAvailabilityDateResultDto(date: '2026-08-01', error: null));

        $syncService = $this->createMock(DishAvailabilitySyncServiceInterface::class);
        $syncService
            ->expects($this->once())
            ->method('syncForCurrentWeekdayCategoryOffsets')
            ->willReturn(5);

        $menuDateMatcher = $this->callback(
            fn (DateTimeInterface $date): bool => $date->format('Y-m-d') === '2026-08-01',
        );

        $notifier = $this->createMock(MaxMenuAvailabilityNotifierInterface::class);
        $notifier
            ->expects($this->once())
            ->method('notify')
            ->with($menuDateMatcher)
            ->willReturn(1);

        $telegramNotifier = $this->createMock(TelegramMenuAvailabilityNotifierInterface::class);
        $telegramNotifier
            ->expects($this->once())
            ->method('notify')
            ->with($menuDateMatcher)
            ->willReturn(3);

        $managerNotifier = $this->createMock(MaxManagerDailyMenuNotifierInterface::class);
        $managerNotifier
            ->expects($this->once())
            ->method('notify')
            ->with($menuDateMatcher)
            ->willReturn(2);

        $this->app->instance(MenuAvailabilityDateResolverInterface::class, $dateResolver);
        $this->app->instance(DishAvailabilitySyncServiceInterface::class, $syncService);
        $this->app->instance(MaxMenuAvailabilityNotifierInterface::class, $notifier);
        $this->app->instance(TelegramMenuAvailabilityNotifierInterface::class, $telegramNotifier);
        $this->app->instance(MaxManagerDailyMenuNotifierInterface::class, $managerNotifier);

        $exitCode = Artisan::call('food:sync-dish-availability');

        $this->assertSame(0, $exitCode);
    }

    /** Нет offsets на текущий weekday — sync и notify не вызываются, exit SUCCESS. */
    public function test_command_skips_sync_and_notify_when_no_offsets_for_weekday(): void
    {
        $dateResolver = $this->createMock(MenuAvailabilityDateResolverInterface::class);
        $dateResolver
            ->expects($this->once())
            ->method('resolveForCurrentWeekday')
            ->willReturn(new MenuAvailabilityDateResultDto(date: null, error: 'нет данных'));

        $syncService = $this->createMock(DishAvailabilitySyncServiceInterface::class);
        $syncService->expects($this->never())->method('syncForCurrentWeekdayCategoryOffsets');
        $syncService->expects($this->never())->method('syncForDate');
        $syncService->expects($this->never())->method('syncForToday');

        $notifier = $this->createMock(MaxMenuAvailabilityNotifierInterface::class);
        $notifier->expects($this->never())->method('notify');

        $telegramNotifier = $this->createMock(TelegramMenuAvailabilityNotifierInterface::class);
        $telegramNotifier->expects($this->never())->method('notify');

        $managerNotifier = $this->createMock(MaxManagerDailyMenuNotifierInterface::class);
        $managerNotifier->expects($this->never())->method('notify');

        $this->app->instance(MenuAvailabilityDateResolverInterface::class, $dateResolver);
        $this->app->instance(DishAvailabilitySyncServiceInterface::class, $syncService);
        $this->app->instance(MaxMenuAvailabilityNotifierInterface::class, $notifier);
        $this->app->instance(TelegramMenuAvailabilityNotifierInterface::class, $telegramNotifier);
        $this->app->instance(MaxManagerDailyMenuNotifierInterface::class, $managerNotifier);

        $exitCode = Artisan::call('food:sync-dish-availability');

        $this->assertSame(0, $exitCode);
    }
}
