<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Order\OrderListItemDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Max\MaxUser;

/**
 * Выборка заказов клиента для API MAX mini-app.
 */
interface CustomerOrderQueryServiceInterface
{
    /**
     * @return list<OrderListItemDto>
     */
    public function list(MaxUser $customer): array;

    /**
     * @throws FoodDomainException
     */
    public function show(MaxUser $customer, int $orderId): OrderDto;
}
