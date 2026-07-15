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
     * @param  list<DeliveryTierDto>  $tiers  отсортированы по убыванию min_items_total
     */
    public function resolve(float $itemsTotal, array $tiers): float
    {
        $currentTier = $this->resolveCurrentTier($itemsTotal, $tiers);

        return $currentTier?->deliveryCost ?? 0.0;
    }

    /**
     * Возвращает следующий (более выгодный) тариф, до которого не хватает суммы заказа.
     *
     * @param  list<DeliveryTierDto>  $tiers  отсортированы по убыванию min_items_total
     */
    public function resolveNextTier(float $itemsTotal, array $tiers): ?DeliveryTierDto
    {
        if ($tiers === []) {
            return null;
        }

        foreach ($tiers as $index => $tier) {
            if ($itemsTotal >= $tier->minItemsTotal) {
                if ($index === 0) {
                    return null;
                }

                return $tiers[$index - 1];
            }
        }

        return $tiers[0];
    }

    /**
     * Определяет текущий тариф доставки для суммы заказа.
     *
     * @param  list<DeliveryTierDto>  $tiers
     */
    private function resolveCurrentTier(float $itemsTotal, array $tiers): ?DeliveryTierDto
    {
        foreach ($tiers as $tier) {
            if ($itemsTotal >= $tier->minItemsTotal) {
                return $tier;
            }
        }

        return null;
    }
}
