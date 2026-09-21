<?php

declare(strict_types=1);

namespace App\Contracts\Food\Delivery;

use App\DTO\Food\Delivery\DeliveryTierDto;

/**
 * Определение применимости и стоимости доставки по тарифам.
 */
interface DeliveryCostResolverInterface
{
    /**
     * Проверяет, доступна ли доставка при наличии категории клиента.
     */
    public function isApplicable(?int $customerCategoryId): bool;

    /**
     * Подбирает стоимость доставки по сумме заказа и тарифам.
     *
     * @param  list<DeliveryTierDto>  $tiers  отсортированы по убыванию min_items_total
     */
    public function resolve(float $itemsTotal, array $tiers): float;

    /**
     * Возвращает следующий (более выгодный) тариф, до которого не хватает суммы заказа.
     *
     * @param  list<DeliveryTierDto>  $tiers  отсортированы по убыванию min_items_total
     */
    public function resolveNextTier(float $itemsTotal, array $tiers): ?DeliveryTierDto;
}
