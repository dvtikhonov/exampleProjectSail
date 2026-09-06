<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Cart\CartRecord;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Создание заказа из снимка черновика корзины.
 */
interface OrderFromCartCreatorInterface
{
    /**
     * Создаёт заказ из черновика корзины.
     *
     * @param  string|null  $deliveryDate  явная дата Y-m-d либо null (дата доступности меню)
     * @param  bool  $draftAfterScanning  true — статус draft_after_scanning, адрес может быть пустым
     * @return array{order: FoodOrderRecord, dto: OrderDto}
     *
     * @throws FoodDomainException
     */
    public function create(
        ?CartRecord $cart,
        int $customerMaxUserId,
        bool $isManual,
        ?int $createdByMaxUserId,
        ?string $deliveryDate = null,
        bool $draftAfterScanning = false,
    ): array;
}
