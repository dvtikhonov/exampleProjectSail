<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;
use App\Enums\Food\Order\AdminOrderListScope;
use App\Enums\Food\Review\OrderReviewStatus;

/**
 * Чтение заказов еды для административного API проверки (address / composition / all).
 */
interface FoodOrderAdminReviewReadRepositoryInterface
{
    /**
     * Находит заказ по идентификатору.
     */
    public function findById(int $id): ?FoodOrderRecord;

    /**
     * Находит заказ по id, если он входит в историю/очередь указанного admin scope.
     * Заказы вне scope (например draft_after_scanning) → null.
     */
    public function findByIdForScope(int $id, AdminOrderListScope $scope): ?FoodOrderRecord;

    /**
     * Постраничный список заказов для проверки адреса с указанным статусом этапа.
     * Исключает rejected, confirmed и draft_after_scanning.
     *
     * @return PaginatedResultDto<FoodOrderRecord>
     */
    public function paginateForAddressReview(OrderReviewStatus $reviewStatus, int $perPage): PaginatedResultDto;

    /**
     * Постраничный список заказов для проверки состава с указанным статусом этапа.
     * Исключает rejected, confirmed и draft_after_scanning.
     *
     * @return PaginatedResultDto<FoodOrderRecord>
     */
    public function paginateForCompositionReview(OrderReviewStatus $reviewStatus, int $perPage): PaginatedResultDto;

    /**
     * История address-scope (address/payment): без фильтра Pending, включая confirmed/rejected.
     * Исключает draft_after_scanning.
     *
     * @return PaginatedResultDto<FoodOrderRecord>
     */
    public function paginateForAddressReviewAll(int $perPage): PaginatedResultDto;

    /**
     * История composition-scope: без фильтра Pending, включая confirmed/rejected.
     * Исключает draft_after_scanning.
     *
     * @return PaginatedResultDto<FoodOrderRecord>
     */
    public function paginateForCompositionReviewAll(int $perPage): PaginatedResultDto;
}
