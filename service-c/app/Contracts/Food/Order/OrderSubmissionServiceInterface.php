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
     * @param  string|null  $deliveryDate  дата доставки Y-m-d; null — дата доступности меню
     *
     * @throws FoodDomainException
     */
    public function submitManual(
        MaxUser $customer,
        MaxUser $manager,
        ?string $deliveryDate = null,
    ): OrderDto;

    /**
     * Создаёт ручной заказ в статусе «Черновик после сканирования» без уведомлений и без approve этапов.
     *
     * @param  string|null  $deliveryDate  дата доставки Y-m-d; null — дата доступности меню
     *
     * @throws FoodDomainException
     */
    public function submitDraftAfterScanning(
        MaxUser $customer,
        MaxUser $manager,
        ?string $deliveryDate = null,
    ): OrderDto;
}
