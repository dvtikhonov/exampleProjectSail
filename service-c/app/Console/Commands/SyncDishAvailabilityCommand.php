<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Max\MaxManagerDailyMenuNotifierInterface;
use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Services\Food\Menu\DishAvailabilitySyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Artisan-команда синхронизации is_available по графику доступности блюд.
 */
class SyncDishAvailabilityCommand extends Command
{
    protected $signature = 'food:sync-dish-availability';

    protected $description = 'Синхронизировать is_available блюд по offsets категорий (текущий weekday MSK), уведомить MAX_REPORT_*/клиентов и отправить меню max_manager';

    /**
     * Синхронизирует доступность блюд и отправляет уведомления в MAX.
     *
     * Если на текущий weekday нет availability_offsets — sync и notify пропускаются (SUCCESS).
     * Sync: сначала сброс is_available у всех блюд; затем для каждой категории
     * дата = сегодня + offset_days и включение по графику.
     * Уведомления: одна агрегированная дата «Блюда на» из resolveForCurrentWeekday().
     */
    public function handle(
        MenuAvailabilityDateResolverInterface $dateResolver,
        DishAvailabilitySyncService $syncService,
        MaxMenuAvailabilityNotifierInterface $notifier,
        MaxManagerDailyMenuNotifierInterface $managerMenuNotifier,
    ): int {
        $result = $dateResolver->resolveForCurrentWeekday();

        if ($result->date === null) {
            $this->warn('Нет availability_offsets на текущий weekday (MSK) — sync и уведомления пропущены.');

            return self::SUCCESS;
        }

        $updatedCount = $syncService->syncForCurrentWeekdayCategoryOffsets();

        $this->info("Дата меню (уведомления): {$result->date}. Обновлено блюд: {$updatedCount}");

        $menuDate = CarbonImmutable::parse($result->date, 'Europe/Moscow');
        $sentCount = $notifier->notify($menuDate);

        if ($sentCount > 0) {
            $this->info("Уведомление о доступности меню отправлено в MAX ({$sentCount}).");
        } else {
            $this->warn('Уведомление о доступности меню не отправлено (бот, получатели или MAX API).');
        }

        $managerSentCount = $managerMenuNotifier->notify($menuDate);

        if ($managerSentCount > 0) {
            $this->info("Меню дня отправлено max_manager в MAX ({$managerSentCount}).");
        } else {
            $this->warn('Меню дня для max_manager не отправлено (бот, получатели или MAX API).');
        }

        return self::SUCCESS;
    }
}
