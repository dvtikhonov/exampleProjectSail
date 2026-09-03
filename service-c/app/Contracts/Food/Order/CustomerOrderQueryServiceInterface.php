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
     * @return list<OrderListItemDto>
     */
    public function list(MaxUserIdentity $customer): array;

    /**
     * @throws FoodDomainException
     */
    public function show(MaxUserIdentity $customer, int $orderId): OrderDto;
}
