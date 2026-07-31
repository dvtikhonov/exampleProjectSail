<?php

declare(strict_types=1);

namespace App\Repositories\Food\Order;

use App\Contracts\Food\Order\FoodOrderAdminReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderCustomerReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Models\Food\FoodOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-реализация репозитория заказов еды.
 */
class EloquentFoodOrderRepository implements FoodOrderAdminReadRepositoryInterface, FoodOrderCustomerReadRepositoryInterface, FoodOrderWriteRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function create(array $attributes): FoodOrder
    {
        return FoodOrder::query()->create($attributes);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?FoodOrder
    {
        return FoodOrder::query()
            ->with(['restaurant', 'maxUser'])
            ->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdForUpdate(int $id): ?FoodOrder
    {
        return FoodOrder::query()
            ->lockForUpdate()
            ->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function update(FoodOrder $order, array $attributes): FoodOrder
    {
        $order->update($attributes);

        return $order->refresh();
    }

    /**
     * {@inheritDoc}
     */
    public function findForAddressReview(OrderReviewStatus $reviewStatus): array
    {
        $query = FoodOrder::query()
            ->with(['restaurant', 'maxUser'])
            ->whereNotIn('status', [OrderStatus::Rejected, OrderStatus::Confirmed]);

        if ($reviewStatus === OrderReviewStatus::Pending) {
            $query->where(function ($builder): void {
                $builder
                    ->where('address_review_status', OrderReviewStatus::Pending)
                    ->orWhere('payment_review_status', OrderReviewStatus::Pending);
            });
        } else {
            $query->where(function ($builder) use ($reviewStatus): void {
                $builder
                    ->where('address_review_status', $reviewStatus)
                    ->orWhere('payment_review_status', $reviewStatus);
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function findForCompositionReview(OrderReviewStatus $reviewStatus): array
    {
        $query = FoodOrder::query()
            ->with(['restaurant', 'maxUser'])
            ->whereNotIn('status', [OrderStatus::Rejected, OrderStatus::Confirmed]);

        if ($reviewStatus === OrderReviewStatus::Pending) {
            $query->where(function ($builder): void {
                $builder
                    ->where('composition_review_status', OrderReviewStatus::Pending)
                    ->orWhere('composition_review_status', OrderReviewStatus::NotApplicable);
            });
        } else {
            $query->where('composition_review_status', $reviewStatus);
        }

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->all();
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
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(): array
    {
        return FoodOrder::query()
            ->with(['restaurant', 'maxUser'])
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function paginateManualOrders(
        ?string $query,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
        ?int $customerMaxUserId = null,
        ?OrderStatus $status = null,
    ): LengthAwarePaginator {
        return $this->manualOrdersQuery(
            $query,
            $dateFrom,
            $dateTo,
            $customerMaxUserId,
            $status,
        )
            ->with(['restaurant', 'maxUser'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function sumManualOrdersTotal(
        ?string $query,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $customerMaxUserId = null,
        ?OrderStatus $status = null,
    ): string {
        $sum = $this->manualOrdersQuery(
            $query,
            $dateFrom,
            $dateTo,
            $customerMaxUserId,
            $status,
        )->sum('total');

        return number_format((float) $sum, 2, '.', '');
    }

    /**
     * Базовый запрос ручных заказов с фильтрами списка.
     *
     * @return Builder<FoodOrder>
     */
    private function manualOrdersQuery(
        ?string $query,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $customerMaxUserId = null,
        ?OrderStatus $status = null,
    ): Builder {
        $builder = FoodOrder::query()->where('is_manual', true);

        if ($customerMaxUserId !== null) {
            $builder->where('max_user_id', $customerMaxUserId);
        }

        if ($status !== null) {
            $builder->where('status', $status);
        }

        if ($dateFrom !== null) {
            $builder->where('created_at', '>=', $dateFrom.' 00:00:00');
        }

        if ($dateTo !== null) {
            $builder->where('created_at', '<=', $dateTo.' 23:59:59');
        }

        $normalizedQuery = $query !== null ? trim($query) : '';

        if ($normalizedQuery !== '') {
            $like = '%'.$normalizedQuery.'%';

            $builder->whereHas('maxUser', function (Builder $userQuery) use ($normalizedQuery, $like): void {
                $userQuery->where(function (Builder $searchQuery) use ($normalizedQuery, $like): void {
                    $searchQuery
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhereRaw(
                            "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?",
                            [$like],
                        );

                    if (ctype_digit($normalizedQuery)) {
                        $searchQuery->orWhere('max_user_id', (int) $normalizedQuery);
                    }
                });
            });
        }

        return $builder;
    }

    /**
     * {@inheritDoc}
     */
    public function findManualOrderById(int $id): ?FoodOrder
    {
        return FoodOrder::query()
            ->with(['restaurant', 'maxUser'])
            ->where('is_manual', true)
            ->whereKey($id)
            ->first();
    }
}
