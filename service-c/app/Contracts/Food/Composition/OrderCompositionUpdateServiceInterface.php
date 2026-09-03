<?php

declare(strict_types=1);

namespace App\Contracts\Food\Composition;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;

/**
 * Обновление состава заказа в очереди проверки composition_reviewer.
 */
interface OrderCompositionUpdateServiceInterface
{
    /**
     * Обновляет состав заказа и пересчитывает суммы.
     *
     * @param  list<array{dish_id: int, quantity: int}>  $items
     *
     * @throws FoodDomainException
     */
    public function update(int $orderId, MaxUserIdentity $admin, array $items): FoodOrderRecord;
}
