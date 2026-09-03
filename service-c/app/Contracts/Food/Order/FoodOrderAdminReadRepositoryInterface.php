<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;

/**
 * Чтение заказов еды для административного API проверки.
 */
interface FoodOrderAdminReadRepositoryInterface
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

    /**
     * Постраничный список ручных заказов с фильтром по потребителю, периоду, статусу и/или ФИО.
     *
     * @return PaginatedResultDto<FoodOrderRecord>
     */
    public function paginateManualOrders(
        ?string $query,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
        ?int $customerMaxUserId = null,
        ?OrderStatus $status = null,
    ): PaginatedResultDto;

    /**
     * Сумма total по всем ручным заказам с теми же фильтрами, что и у списка.
     */
    public function sumManualOrdersTotal(
        ?string $query,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $customerMaxUserId = null,
        ?OrderStatus $status = null,
    ): string;

    /**
     * Находит ручной заказ по идентификатору.
     */
    public function findManualOrderById(int $id): ?FoodOrderRecord;
}
