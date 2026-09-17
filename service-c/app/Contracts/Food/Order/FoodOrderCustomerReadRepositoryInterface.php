<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Shared\PaginatedResultDto;

/**
 * Чтение заказов еды для клиентского API MAX mini-app.
 */
interface FoodOrderCustomerReadRepositoryInterface
{
    /**
     * Находит заказ по идентификатору.
     */
    public function findById(int $id): ?FoodOrderRecord;

    /**
     * Постраничный список заказов клиента (новые первыми).
     *
     * @return PaginatedResultDto<FoodOrderRecord>
     */
    public function paginateByMaxUserId(int $maxUserId, int $perPage, int $page): PaginatedResultDto;
}
