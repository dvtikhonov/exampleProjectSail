<?php

declare(strict_types=1);

namespace App\Repositories\Food\Order;

use App\Contracts\Food\Order\FoodOrderAdminReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderAdminReviewReadRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderManualAdminReadRepositoryInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;
use App\Enums\Food\Order\AdminOrderListScope;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;

/**
 * Composition-адаптер полного admin-read порта: делегирует в review / manual репозитории.
 */
class EloquentFoodOrderAdminReadRepository implements FoodOrderAdminReadRepositoryInterface
{
    public function __construct(
        private readonly FoodOrderAdminReviewReadRepositoryInterface $reviewReadRepository,
        private readonly FoodOrderManualAdminReadRepositoryInterface $manualReadRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?FoodOrderRecord
    {
        return $this->reviewReadRepository->findById($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdForScope(int $id, AdminOrderListScope $scope): ?FoodOrderRecord
    {
        return $this->reviewReadRepository->findByIdForScope($id, $scope);
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForAddressReview(OrderReviewStatus $reviewStatus, int $perPage): PaginatedResultDto
    {
        return $this->reviewReadRepository->paginateForAddressReview($reviewStatus, $perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForCompositionReview(OrderReviewStatus $reviewStatus, int $perPage): PaginatedResultDto
    {
        return $this->reviewReadRepository->paginateForCompositionReview($reviewStatus, $perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForAddressReviewAll(int $perPage): PaginatedResultDto
    {
        return $this->reviewReadRepository->paginateForAddressReviewAll($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForCompositionReviewAll(int $perPage): PaginatedResultDto
    {
        return $this->reviewReadRepository->paginateForCompositionReviewAll($perPage);
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
        return $this->manualReadRepository->paginateManualOrders(
            $query,
            $dateFrom,
            $dateTo,
            $perPage,
            $customerMaxUserId,
            $status,
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
        return $this->manualReadRepository->sumManualOrdersTotal(
            $query,
            $dateFrom,
            $dateTo,
            $customerMaxUserId,
            $status,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function findManualOrderById(int $id): ?FoodOrderRecord
    {
        return $this->manualReadRepository->findManualOrderById($id);
    }
}
