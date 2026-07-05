<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\OrderDto;
use App\DTO\Food\OrderListItemDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\MaxUser;

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
