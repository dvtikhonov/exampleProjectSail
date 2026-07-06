<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Food\DishAvailabilityRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Синхронизирует max_dishes.is_available по графику доступности на сегодня (MSK).
 */
class SyncDishAvailabilityCommand extends Command
{
    private const string TIMEZONE = 'Europe/Moscow';

    protected $signature = 'food:sync-dish-availability';

    protected $description = 'Синхронизировать is_available блюд по графику на сегодня (Europe/Moscow)';

    /**
     * Обновляет флаг доступности всех активных блюд по записям графика на текущую дату.
     */
    public function handle(DishAvailabilityRepositoryInterface $availabilityRepository): int
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();
        $updated = $availabilityRepository->syncDishesIsAvailableForDate($today);

        $this->info(sprintf(
            'Синхронизация доступности блюд на %s: обновлено %d.',
            $today,
            $updated,
        ));

        return self::SUCCESS;
    }
}
