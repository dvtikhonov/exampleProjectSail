<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Order\OrderListItemDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;

/**
 * Выборка заказов клиента для API MAX mini-app.
 */
interface CustomerOrderQueryServiceInterface
{
    /**
     * Постраничный список заказов клиента.
     *
     * @return array{
     *     orders: list<OrderListItemDto>,
     *     meta: array{current_page: int, per_page: int, total: int, last_page: int}
     * }
     */
    public function list(MaxUserIdentity $customer, int $perPage, int $page): array;

    /**
     * @throws FoodDomainException
     */
    public function show(MaxUserIdentity $customer, int $orderId): OrderDto;
}
