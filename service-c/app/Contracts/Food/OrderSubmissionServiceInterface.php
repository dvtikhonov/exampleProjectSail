<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\OrderDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\MaxUser;

/**
 * Оформление заказа из черновика корзины.
 */
interface OrderSubmissionServiceInterface
{
    /**
     * Создаёт заказ из корзины пользователя.
     *
     * @throws FoodDomainException
     */
    public function submit(MaxUser $maxUser): OrderDto;
}
