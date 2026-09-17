<?php

declare(strict_types=1);

namespace App\Repositories\Food\Order;

use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;
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
    public function paginateByMaxUserId(int $maxUserId, int $perPage, int $page): PaginatedResultDto
    {
        $paginator = FoodOrder::query()
            ->with(['restaurant'])
            ->where('max_user_id', $maxUserId)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        /** @var list<FoodOrderRecord> $records */
        $records = $paginator->getCollection()
            ->map(fn (FoodOrder $model): FoodOrderRecord => $this->mapToRecord($model))
            ->values()
            ->all();

        return new PaginatedResultDto(
            items: $records,
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
        );
    }
}
