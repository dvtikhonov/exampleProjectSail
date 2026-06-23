<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\DTO\Food\DeliveryTierDto;
use App\Models\MaxUser;

/**
 * Определение применимости и стоимости доставки по тарифам.
 */
class DeliveryCostResolver
{
    /**
     * Проверяет, доступна ли доставка для категории пользователя.
     */
    public function isApplicable(MaxUser $maxUser): bool
    {
        $maxUser->loadMissing('customerCategory');

        return $maxUser->customer_category_id !== null && $maxUser->customerCategory !== null;
    }

    /**
     * Подбирает стоимость доставки по сумме заказа и тарифам.
     *
     * @param  list<DeliveryTierDto>  $tiers
     */
    public function resolve(float $itemsTotal, array $tiers): float
    {
        if ($tiers === []) {
            return 0.0;
        }

        foreach ($tiers as $tier) {
            if ($itemsTotal >= $tier->minItemsTotal) {
                return $tier->deliveryCost;
            }
        }

        return 0.0;
    }
}
