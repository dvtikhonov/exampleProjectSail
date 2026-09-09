<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Order\FoodOrderAdminReviewReadRepositoryInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;
use App\Enums\Food\Order\AdminOrderListScope;
use App\Enums\Food\Order\AdminOrderListStatus;
use App\Enums\Food\Review\OrderReviewStatus;

/**
 * Выбор постраничной выборки заказов для админского списка по scope и статусу.
 */
class AdminOrderReviewListResolver
{
    public function __construct(
        private readonly FoodOrderAdminReviewReadRepositoryInterface $foodOrderReadRepository,
    ) {}

    /**
     * Возвращает постраничный результат репозитория для пары scope/status.
     *
     * @return PaginatedResultDto<FoodOrderRecord>
     */
    public function resolve(
        AdminOrderListScope $scope,
        AdminOrderListStatus $status,
        int $perPage,
    ): PaginatedResultDto {
        return match ($status) {
            AdminOrderListStatus::Pending => match ($scope) {
                AdminOrderListScope::Address => $this->foodOrderReadRepository->paginateForAddressReview(
                    OrderReviewStatus::Pending,
                    $perPage,
                ),
                AdminOrderListScope::Composition => $this->foodOrderReadRepository->paginateForCompositionReview(
                    OrderReviewStatus::Pending,
                    $perPage,
                ),
            },
            AdminOrderListStatus::All => $this->foodOrderReadRepository->paginateAll($perPage),
        };
    }
}
