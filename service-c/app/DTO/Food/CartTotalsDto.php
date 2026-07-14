<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Рассчитанные суммы корзины: блюда, доставка и итог.
 */
readonly class CartTotalsDto
{
    public function __construct(
        public float $itemsTotal,
        public ?float $deliveryCost,
        public float $total,
        public bool $deliveryApplicable,
        public ?CustomerCategoryDto $customerCategory,
        public ?float $nextTierMinTotal = null,
        public ?float $nextTierDeliveryCost = null,
        public ?float $amountToNextTier = null,
    ) {}
}
