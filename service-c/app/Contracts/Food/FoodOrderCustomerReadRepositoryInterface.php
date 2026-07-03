<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\FoodOrder;

/**
 * Чтение заказов еды для клиентского API MAX mini-app.
 */
interface FoodOrderCustomerReadRepositoryInterface
{
    public function findById(int $id): ?FoodOrder;

    /**
     * Заказы клиента в хронологическом порядке (новые первыми).
     *
     * @return list<FoodOrder>
     */
    public function findByMaxUserId(int $maxUserId): array;
}
