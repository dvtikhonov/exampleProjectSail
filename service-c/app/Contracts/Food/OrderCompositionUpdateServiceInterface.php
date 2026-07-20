<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Exceptions\Food\FoodDomainException;
use App\Models\FoodOrder;
use App\Models\MaxUser;

/**
 * Обновление состава заказа в очереди проверки composition_reviewer.
 */
interface OrderCompositionUpdateServiceInterface
{
    /**
     * Заменяет состав заказа и пересчитывает суммы.
     *
     * @param  list<array{
     *     dish_id: int,
     *     quantity: int,
     *     combo_ref: string|null,
     *     combo_partner_dish_id: int|null
     * }>  $items
     *
     * @throws FoodDomainException
     */
    public function update(int $orderId, MaxUser $admin, array $items): FoodOrder;
}
