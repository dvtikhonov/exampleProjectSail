<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\Enums\Food\Review\OrderReviewStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Models\Food\FoodOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Чтение заказов еды для административного API проверки.
 */
interface FoodOrderAdminReadRepositoryInterface
{
    /**
     * Находит заказ по идентификатору.
     */
    public function findById(int $id): ?FoodOrder;

    /**
     * Заказы для проверки адреса с указанным статусом этапа.
     *
     * @return list<FoodOrder>
     */
    public function findForAddressReview(OrderReviewStatus $reviewStatus): array;

    /**
     * Заказы для проверки состава с указанным статусом этапа.
     *
     * @return list<FoodOrder>
     */
    public function findForCompositionReview(OrderReviewStatus $reviewStatus): array;

    /**
     * Все заказы в хронологическом порядке (новые первыми).
     *
     * @return list<FoodOrder>
     */
    public function findAll(): array;

    /**
     * Постраничный список ручных заказов с фильтром по потребителю, периоду, статусу и/или ФИО.
     *
     * @return LengthAwarePaginator<int, FoodOrder>
     */
    public function paginateManualOrders(
        ?string $query,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
        ?int $customerMaxUserId = null,
        ?OrderStatus $status = null,
    ): LengthAwarePaginator;

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
    public function findManualOrderById(int $id): ?FoodOrder;
}
