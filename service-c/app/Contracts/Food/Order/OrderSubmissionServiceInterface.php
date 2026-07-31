<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\OrderDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Max\MaxUser;

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

    /**
     * Создаёт ручной заказ из корзины менеджера от имени клиента.
     * Сразу подтверждает адрес, оплату и состав (approved) и переводит заказ в confirmed.
     *
     * @throws FoodDomainException
     */
    public function submitManual(MaxUser $customer, MaxUser $manager): OrderDto;
}
