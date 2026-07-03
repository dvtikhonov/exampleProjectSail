<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Models\FoodOrder;

/**
 * Запись и блокирующее чтение заказов еды MAX mini-app.
 */
interface FoodOrderWriteRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FoodOrder;

    public function findByIdForUpdate(int $id): ?FoodOrder;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(FoodOrder $order, array $attributes): FoodOrder;
}
