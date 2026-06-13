<?php

declare(strict_types=1);

namespace App\DTO\Food;

readonly class DeliveryTierDto
{
    public function __construct(
        public float $minItemsTotal,
        public float $deliveryCost,
    ) {}
}
