<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;
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
     * Постраничный список всех заказов в хронологическом порядке (новые первыми).
     *
     * @return PaginatedResultDto<FoodOrderRecord>
     */
    public function paginateAll(int $perPage): PaginatedResultDto;
}
