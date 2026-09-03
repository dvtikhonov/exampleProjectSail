<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Чтение заказов еды для клиентского API MAX mini-app.
 */
interface FoodOrderCustomerReadRepositoryInterface
{
    /**
     * Находит заказ по идентификатору.
     */
    public function findById(int $id): ?FoodOrderRecord;

    /**
     * Заказы клиента в хронологическом порядке (новые первыми).
     *
     * @return list<FoodOrderRecord>
     */
    public function findByMaxUserId(int $maxUserId): array;
}
