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
     * Создаёт заказ еды.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FoodOrder;

    /**
     * Находит заказ по ID с блокировкой строки (SELECT … FOR UPDATE).
     */
    public function findByIdForUpdate(int $id): ?FoodOrder;

    /**
     * Обновляет заказ еды.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(FoodOrder $order, array $attributes): FoodOrder;
}
