<?php

declare(strict_types=1);

namespace App\Repositories\Food\Order;

use App\Contracts\Food\Order\FoodOrderAdminReadRepositoryInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Models\Food\FoodOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-реализация чтения заказов еды для административного API.
 */
class EloquentFoodOrderAdminReadRepository implements FoodOrderAdminReadRepositoryInterface
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
    public function paginateForAddressReview(OrderReviewStatus $reviewStatus, int $perPage): PaginatedResultDto
    {
        $query = FoodOrder::query()
            ->with(['restaurant', 'maxUser'])
            ->whereNotIn('status', $this->statusesExcludedFromReviewQueue());

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

        return $this->paginateRecords(
            $query
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            $perPage,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForCompositionReview(OrderReviewStatus $reviewStatus, int $perPage): PaginatedResultDto
    {
        $query = FoodOrder::query()
            ->with(['restaurant', 'maxUser'])
            ->whereNotIn('status', $this->statusesExcludedFromReviewQueue());

        if ($reviewStatus === OrderReviewStatus::Pending) {
            $query->where(function ($builder): void {
                $builder
                    ->where('composition_review_status', OrderReviewStatus::Pending)
                    ->orWhere('composition_review_status', OrderReviewStatus::NotApplicable);
            });
        } else {
            $query->where('composition_review_status', $reviewStatus);
        }

        return $this->paginateRecords(
            $query
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            $perPage,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function paginateAll(int $perPage): PaginatedResultDto
    {
        return $this->paginateRecords(
            FoodOrder::query()
                ->with(['restaurant', 'maxUser'])
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            $perPage,
        );
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
    ): PaginatedResultDto {
        return $this->paginateRecords(
            $this->manualOrdersQuery(
                $query,
                $dateFrom,
                $dateTo,
                $customerMaxUserId,
                $status,
            )
                ->with(['restaurant', 'maxUser'])
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            $perPage,
        );
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
     * {@inheritDoc}
     */
    public function findManualOrderById(int $id): ?FoodOrderRecord
    {
        $model = FoodOrder::query()
            ->with(['restaurant', 'maxUser'])
            ->withExists('messages')
            ->where('is_manual', true)
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->mapToRecord($model) : null;
    }

    /**
     * Статусы, которые не попадают в очередь «Проверка заказов».
     *
     * @return list<OrderStatus>
     */
    private function statusesExcludedFromReviewQueue(): array
    {
        return [
            OrderStatus::Rejected,
            OrderStatus::Confirmed,
            OrderStatus::DraftAfterScanning,
        ];
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
}
