<?php

declare(strict_types=1);

namespace App\Repositories\Food;

use App\Contracts\Food\FoodOrderAdminReadRepositoryInterface;
use App\Contracts\Food\FoodOrderCustomerReadRepositoryInterface;
use App\Contracts\Food\FoodOrderWriteRepositoryInterface;
use App\Enums\Food\OrderReviewStatus;
use App\Enums\Food\OrderStatus;
use App\Models\FoodOrder;

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
}
