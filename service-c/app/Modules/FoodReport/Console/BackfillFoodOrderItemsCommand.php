<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Console;

use App\Modules\FoodReport\Contracts\FoodOrderConfirmedBackfillSourceInterface;
use App\Contracts\Food\Order\FoodOrderItemSyncServiceInterface;
use Illuminate\Console\Command;

/**
 * Backfill max_food_order_items только для confirmed-заказов.
 *
 * Запуск на shared/prod — только после отдельного согласия (и после migrate).
 */
class BackfillFoodOrderItemsCommand extends Command
{
    protected $signature = 'food-report:backfill-order-items
                            {--chunk=100 : Размер chunk при обходе confirmed-заказов}';

    protected $description = 'Синхронизировать max_food_order_items только для заказов со status=confirmed';

    /**
     * Обходит confirmed-заказы и вызывает syncIfConfirmed.
     */
    public function handle(
        FoodOrderConfirmedBackfillSourceInterface $source,
        FoodOrderItemSyncServiceInterface $syncService,
    ): int {
        $chunkSize = max(1, (int) $this->option('chunk'));

        $count = $source->eachConfirmed(
            static function ($order) use ($syncService): void {
                $syncService->syncIfConfirmed($order);
            },
            $chunkSize,
        );

        $this->info("Backfill завершён: синхронизировано confirmed-заказов: {$count}.");

        return self::SUCCESS;
    }
}
