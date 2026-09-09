<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;
use App\Enums\Food\Order\OrderStatus;

/**
 * Чтение ручных заказов для административного API (max_manager).
 */
interface FoodOrderManualAdminReadRepositoryInterface
{
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
