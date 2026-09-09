<?php

declare(strict_types=1);

namespace App\Repositories\Food\Order;

use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Models\Food\FoodOrder;

/**
 * Eloquent-реализация чтения заказов еды для клиентского API.
 */
class EloquentFoodOrderCustomerReadRepository implements FoodOrderCustomerReadRepositoryInterface
{
    use MapsFoodOrderEloquent;

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?FoodOrderRecord
    {
        $model = FoodOrder::query()
            ->with(['restaurant', 'maxUser'])
            ->find($id);

        return $model !== null ? $this->mapToRecord($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findByMaxUserId(int $maxUserId): array
    {
        return FoodOrder::query()
            ->with(['restaurant'])
            ->where('max_user_id', $maxUserId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FoodOrder $model): FoodOrderRecord => $this->mapToRecord($model))
            ->all();
    }
}
