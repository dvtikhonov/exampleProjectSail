<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Max\MaxManagerDailyMenuNotifierInterface;
use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Services\Food\DishAvailabilitySyncService;
use Illuminate\Console\Command;

/**
 * Artisan-команда синхронизации is_available по графику доступности блюд.
 */
class SyncDishAvailabilityCommand extends Command
{
    protected $signature = 'food:sync-dish-availability';

    protected $description = 'Синхронизировать is_available блюд по графику на сегодня (MSK), уведомить MAX_REPORT_*/клиентов и отправить меню max_manager';

    /**
     * Синхронизирует доступность блюд и отправляет уведомления в MAX.
     */
    public function handle(
        DishAvailabilitySyncService $syncService,
        MaxMenuAvailabilityNotifierInterface $notifier,
        MaxManagerDailyMenuNotifierInterface $managerMenuNotifier,
    ): int {
        $updatedCount = $syncService->syncForToday();

        $this->info("Обновлено блюд: {$updatedCount}");

        $sentCount = $notifier->notify();

        if ($sentCount > 0) {
            $this->info("Уведомление о доступности меню отправлено в MAX ({$sentCount}).");
        } else {
            $this->warn('Уведомление о доступности меню не отправлено (бот, получатели или MAX API).');
        }

        $managerSentCount = $managerMenuNotifier->notify();

        if ($managerSentCount > 0) {
            $this->info("Меню дня отправлено max_manager в MAX ({$managerSentCount}).");
        } else {
            $this->warn('Меню дня для max_manager не отправлено (бот, получатели или MAX API).');
        }

        return self::SUCCESS;
    }
}
