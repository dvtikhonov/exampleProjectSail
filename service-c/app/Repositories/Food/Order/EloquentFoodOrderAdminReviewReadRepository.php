<?php

declare(strict_types=1);

namespace App\Repositories\Food\Order;

use App\Contracts\Food\Order\FoodOrderAdminReviewReadRepositoryInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;
use App\Enums\Food\Order\AdminOrderListScope;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Models\Food\FoodOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-реализация чтения заказов еды для административной проверки (address / composition).
 */
class EloquentFoodOrderAdminReviewReadRepository implements FoodOrderAdminReviewReadRepositoryInterface
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
    public function findByIdForScope(int $id, AdminOrderListScope $scope): ?FoodOrderRecord
    {
        $model = $this->reviewHistoryQuery($scope)
            ->whereKey($id)
            ->first();

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
    public function paginateForAddressReviewAll(int $perPage): PaginatedResultDto
    {
        return $this->paginateRecords(
            $this->reviewHistoryQuery(AdminOrderListScope::Address)
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            $perPage,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForCompositionReviewAll(int $perPage): PaginatedResultDto
    {
        return $this->paginateRecords(
            $this->reviewHistoryQuery(AdminOrderListScope::Composition)
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            $perPage,
        );
    }

    /**
     * Базовый запрос истории проверки в рамках admin scope (status=all / detail).
     * Без фильтра Pending; включает confirmed/rejected; исключает draft_after_scanning.
     *
     * @return Builder<FoodOrder>
     */
    private function reviewHistoryQuery(AdminOrderListScope $scope): Builder
    {
        $query = FoodOrder::query()
            ->with(['restaurant', 'maxUser'])
            ->where('status', '!=', OrderStatus::DraftAfterScanning);

        // Scope-смысл как у pending-очередей: address смотрит address/payment,
        // composition — composition (в т.ч. legacy not_applicable уже в истории).
        return match ($scope) {
            AdminOrderListScope::Address => $query->where(function (Builder $builder): void {
                $builder
                    ->whereNotNull('address_review_status')
                    ->orWhereNotNull('payment_review_status');
            }),
            AdminOrderListScope::Composition => $query->whereNotNull('composition_review_status'),
        };
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
}
