<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\DTO\Food\DeliveryTierDto;

interface DeliveryTierRepositoryInterface
{
    /**
     * @return list<DeliveryTierDto> отсортированы по min_items_total по убыванию
     */
    public function findTiersFor(int $restaurantId, int $customerCategoryId): array;
}
