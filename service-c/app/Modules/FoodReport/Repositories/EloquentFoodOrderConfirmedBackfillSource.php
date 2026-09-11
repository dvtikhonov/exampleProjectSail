<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Repositories;

use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Order\OrderStatus;
use App\Models\Food\FoodOrder;
use App\Modules\FoodReport\Contracts\FoodOrderConfirmedBackfillSourceInterface;
use App\Repositories\Food\Order\FoodOrderMapper;
use Illuminate\Database\Eloquent\Collection;

/**
 * Eloquent-источник confirmed-заказов для backfill max_food_order_items.
 */
final class EloquentFoodOrderConfirmedBackfillSource implements FoodOrderConfirmedBackfillSourceInterface
{
    public function __construct(
        private readonly FoodOrderMapper $mapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function eachConfirmed(callable $callback, int $chunkSize = 100): int
    {
        $count = 0;
        $size = max(1, $chunkSize);

        FoodOrder::query()
            ->where('status', OrderStatus::Confirmed)
            ->orderBy('id')
            ->chunkById($size, function (Collection $orders) use ($callback, &$count): void {
                /** @var FoodOrder $model */
                foreach ($orders as $model) {
                    $callback($this->mapper->toRecord($model));
                    $count++;
                }
            });

        return $count;
    }
}
