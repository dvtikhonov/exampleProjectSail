<?php

declare(strict_types=1);

namespace App\Repositories\Food\Order;

use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\DTO\Food\Order\FoodOrderCreateCommand;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\FoodOrderUpdateCommand;
use App\Models\Food\FoodOrder;

/**
 * Eloquent-реализация записи и блокирующего чтения заказов еды.
 */
class EloquentFoodOrderWriteRepository implements FoodOrderWriteRepositoryInterface
{
    use MapsFoodOrderEloquent;

    /**
     * {@inheritDoc}
     */
    public function create(FoodOrderCreateCommand $command): FoodOrderRecord
    {
        $model = FoodOrder::query()->create($this->mapper->toCreateAttributes($command));

        return $this->mapToRecord($model);
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdForUpdate(int $id): ?FoodOrderRecord
    {
        $model = FoodOrder::query()
            ->lockForUpdate()
            ->find($id);

        return $model !== null ? $this->mapToRecord($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function update(FoodOrderRecord $order, FoodOrderUpdateCommand $command): FoodOrderRecord
    {
        $model = FoodOrder::query()->findOrFail($order->id);
        // Reviewer/rejection-поля вне $fillable — пишем через forceFill.
        $model->forceFill($this->mapper->toUpdateAttributes($command))->save();

        return $this->mapToRecord($model->refresh());
    }

    /**
     * {@inheritDoc}
     */
    public function delete(FoodOrderRecord $order): void
    {
        FoodOrder::query()->whereKey($order->id)->delete();
    }
}
