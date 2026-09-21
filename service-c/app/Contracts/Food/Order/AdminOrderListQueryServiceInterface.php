<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\AdminOrderListItemDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Order\AdminOrderListScope;
use App\Enums\Food\Order\AdminOrderListStatus;
use App\Exceptions\Food\FoodDomainException;

/**
 * Постраничный список заказов для административного API проверки.
 */
interface AdminOrderListQueryServiceInterface
{
    /**
     * Возвращает постраничный список заказов для админского API по scope и статусу.
     *
     * @return array{
     *     orders: list<AdminOrderListItemDto>,
     *     meta: array{current_page: int, per_page: int, total: int, last_page: int}
     * }
     *
     * @throws FoodDomainException
     */
    public function list(
        MaxUserIdentity $admin,
        AdminOrderListScope $scope,
        AdminOrderListStatus $status,
        int $perPage,
    ): array;
}
