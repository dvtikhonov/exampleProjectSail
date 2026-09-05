<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;

/**
 * Оформление клиентского заказа из черновика корзины.
 */
interface CustomerOrderSubmissionServiceInterface
{
    /**
     * Создаёт заказ из корзины пользователя.
     *
     * @throws FoodDomainException
     */
    public function submit(MaxUserIdentity $user): OrderDto;
}
