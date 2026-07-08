<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Food\DishAvailabilitySyncService;
use App\Services\Max\UiStand\MaxMenuAvailabilityNotifier;
use Illuminate\Console\Command;

/**
 * Artisan-команда синхронизации is_available по графику доступности блюд.
 */
class SyncDishAvailabilityCommand extends Command
{
    protected $signature = 'food:sync-dish-availability';

    protected $description = 'Синхронизировать is_available блюд по графику на сегодня (MSK) и уведомить UI Stand';

    /**
     * Синхронизирует доступность блюд и отправляет уведомление в MAX.
     */
    public function handle(
        DishAvailabilitySyncService $syncService,
        MaxMenuAvailabilityNotifier $notifier,
    ): int {
        $updatedCount = $syncService->syncForToday();

        $this->info("Обновлено блюд: {$updatedCount}");

        $notifier->notify();
        $this->info('Уведомление о доступности меню отправлено в MAX UI Stand.');

        return self::SUCCESS;
    }
}
