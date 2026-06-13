<?php

declare(strict_types=1);

namespace App\DTO\Food;

readonly class CartTotalsDto
{
    public function __construct(
        public float $itemsTotal,
        public ?float $deliveryCost,
        public float $total,
        public bool $deliveryApplicable,
        public ?CustomerCategoryDto $customerCategory,
    ) {}
}
